<?php

declare(strict_types=1);

namespace Sofy\Search;

use Sofy\Database\Connection;
use Sofy\Search\Drivers\CollectionDriver;
use Sofy\Search\Drivers\DatabaseDriver;
use Sofy\Search\Drivers\DriverInterface;

/**
 * Search orchestrator. Owns the tokenizer + driver, turns model documents
 * into weighted terms at index time, and turns a query string into ranked
 * results. Resolved once and shared via the Search facade.
 */
final class Engine
{
    private ?DriverInterface $driver = null;
    private ?Tokenizer $tokenizer = null;

    /** @param array<string,mixed> $config */
    public function __construct(private array $config = []) {}

    // ── Indexing ────────────────────────────────────────────────────────────

    /**
     * (Re)index a single document. $document maps field => text; $weights maps
     * field => weight (defaults to 1). Stored under $index keyed by $docId.
     *
     * @param array<string,scalar|null> $document
     * @param array<string,float|int>   $weights
     */
    public function put(string $index, string $docId, array $document, array $weights = []): void
    {
        $terms = $this->buildTerms($document, $weights);
        $this->driver()->put($index, $docId, $terms);
    }

    public function remove(string $index, string $docId): void
    {
        $this->driver()->remove($index, $docId);
    }

    public function flush(?string $index = null): void
    {
        $this->driver()->flush($index);
    }

    /**
     * Index a Searchable model instance using its configured field weights.
     */
    public function indexModel(object $model): void
    {
        [$index, $docId, $document, $weights] = $this->describe($model);
        $this->put($index, $docId, $document, $weights);
    }

    public function unindexModel(object $model): void
    {
        [$index, $docId] = $this->describe($model);
        $this->remove($index, $docId);
    }

    /**
     * Bulk-index every row of a Searchable model. Returns the count indexed.
     *
     * @param class-string $modelClass
     */
    public function import(string $modelClass): int
    {
        $n = 0;
        foreach ($modelClass::all() as $model) {
            $this->indexModel($model);
            $n++;
        }
        return $n;
    }

    // ── Querying ────────────────────────────────────────────────────────────

    /**
     * Search an index by free text. When $index is a Searchable model class,
     * the result hydrates models in ranked order.
     */
    public function search(string $index, string $query, int $limit = 20, int $offset = 0): SearchResult
    {
        [$terms, $prefix] = $this->queryTerms($query);
        if ($terms === [] && ($prefix === null || $prefix === '')) {
            return new SearchResult([], $this->modelFor($index));
        }
        $scores = $this->driver()->query($index, $terms, $prefix, $limit, $offset);
        return new SearchResult($scores, $this->modelFor($index));
    }

    /**
     * One-shot in-memory ranking of an arbitrary collection — no stored index.
     * This is what searchable UI components use: rank the options they already
     * hold against what the user typed. Returns the items ordered by relevance
     * (best first); a blank query returns the items unchanged.
     *
     * @param iterable<int,mixed>       $items
     * @param callable(mixed):string    $textOf   extract searchable text from an item
     * @return list<mixed>
     */
    public function rank(iterable $items, string $query, callable $textOf, int $limit = 50): array
    {
        $list = is_array($items) ? array_values($items) : iterator_to_array($items, false);

        $q = trim($query);
        if ($q === '') {
            return array_slice($list, 0, $limit);
        }

        [$terms, $prefix] = $this->queryTerms($q);
        $tok = $this->tokenizer();

        $scored = [];
        foreach ($list as $i => $item) {
            $freq  = $tok->frequencies((string) $textOf($item));
            $score = 0.0;
            foreach ($terms as $t) {
                $score += $freq[$t] ?? 0;
            }
            if ($prefix !== null && $prefix !== '') {
                foreach ($freq as $term => $c) {
                    if (str_starts_with((string) $term, $prefix)) { $score += $c; break; }
                }
            }
            if ($score > 0.0) {
                $scored[] = ['i' => $i, 'item' => $item, 'score' => $score];
            }
        }

        usort($scored, static fn($a, $b) => $b['score'] <=> $a['score'] ?: $a['i'] <=> $b['i']);
        return array_map(static fn($r) => $r['item'], array_slice($scored, 0, $limit));
    }

    // ── Internals ───────────────────────────────────────────────────────────

    /**
     * Build term => weight for a document by tokenizing each field's text and
     * multiplying term frequency by the field's weight.
     *
     * @param array<string,scalar|null> $document
     * @param array<string,float|int>   $weights
     * @return array<string,float>
     */
    private function buildTerms(array $document, array $weights): array
    {
        $tok   = $this->tokenizer();
        $terms = [];
        foreach ($document as $field => $text) {
            if ($text === null || $text === '') {
                continue;
            }
            $w = (float) ($weights[$field] ?? 1);
            foreach ($tok->frequencies((string) $text) as $term => $freq) {
                $terms[$term] = ($terms[$term] ?? 0.0) + $w * $freq;
            }
        }
        return $terms;
    }

    /**
     * Split a query into exact terms plus an optional prefix (the last token,
     * for autocomplete) when tokenizer.prefix is enabled.
     *
     * @return array{0: list<string>, 1: ?string}
     */
    private function queryTerms(string $query): array
    {
        $tokens = $this->tokenizer()->tokenize($query);
        if ($tokens === []) {
            return [[], null];
        }
        $prefixOn = (bool) ($this->config['tokenizer']['prefix'] ?? true);
        if (!$prefixOn) {
            return [$tokens, null];
        }
        // Treat the last token as a prefix; the rest must match exactly. Only
        // do this when the original query didn't end with a separator (i.e.
        // the user is still typing that word).
        $endsOpen = (bool) preg_match('/[\p{L}\p{N}]$/u', $query);
        if (!$endsOpen) {
            return [$tokens, null];
        }
        $prefix = array_pop($tokens);
        return [$tokens, $prefix];
    }

    /**
     * Describe a Searchable model: [index, docId, document, weights].
     *
     * @return array{0:string,1:string,2:array<string,mixed>,3:array<string,float|int>}
     */
    private function describe(object $model): array
    {
        $index  = method_exists($model, 'searchableIndex') ? $model::searchableIndex() : $model::class;
        $docId  = method_exists($model, 'searchableKey') ? (string) $model->searchableKey()
            : (string) $model->getPrimaryKeyValue();
        $doc    = method_exists($model, 'toSearchableArray') ? $model->toSearchableArray() : $model->toArray();
        $weights = $this->config['indexes'][$model::class]['fields'] ?? [];
        // If weights are configured, restrict the document to those fields.
        if ($weights !== []) {
            $doc = array_intersect_key($doc, $weights);
        }
        return [$index, $docId, $doc, $weights];
    }

    /** Map an index name back to a model class for hydration, if it is one. */
    private function modelFor(string $index): ?string
    {
        return class_exists($index) && is_subclass_of($index, \Sofy\Database\Model::class)
            ? $index
            : null;
    }

    public function tokenizer(): Tokenizer
    {
        return $this->tokenizer ??= new Tokenizer((array) ($this->config['tokenizer'] ?? []));
    }

    public function driver(): DriverInterface
    {
        return $this->driver ??= $this->makeDriver();
    }

    /** Swap the driver — used by tests. */
    public function setDriver(DriverInterface $driver): void
    {
        $this->driver = $driver;
    }

    private function makeDriver(): DriverInterface
    {
        $name = (string) ($this->config['driver'] ?? 'database');
        return match ($name) {
            'collection', 'memory', 'array' => new CollectionDriver(),
            default => new DatabaseDriver(
                Connection::getDefault(),
                (string) ($this->config['index_table'] ?? 'search_index'),
            ),
        };
    }
}
