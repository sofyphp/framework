<?php

declare(strict_types=1);

namespace Sofy\Search;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * A ranked result set. Lazily hydrates models in score order when bound to a
 * model class, otherwise exposes raw doc ids + scores. Iterable and countable.
 *
 * @implements IteratorAggregate<int, mixed>
 */
final class SearchResult implements IteratorAggregate, Countable
{
    /**
     * @param array<string,float> $scores docId => score, already ordered desc
     * @param class-string|null   $model  model to hydrate ids into, if any
     */
    public function __construct(
        private readonly array $scores,
        private readonly ?string $model = null,
    ) {}

    /** @return list<string> doc ids in ranked order */
    public function ids(): array
    {
        return array_map('strval', array_keys($this->scores));
    }

    /** @return array<string,float> docId => score */
    public function scores(): array
    {
        return $this->scores;
    }

    public function score(string $docId): float
    {
        return $this->scores[$docId] ?? 0.0;
    }

    public function isEmpty(): bool
    {
        return $this->scores === [];
    }

    public function count(): int
    {
        return count($this->scores);
    }

    /**
     * Hydrate models in ranked order (preserving score order, dropping ids the
     * DB no longer has). Returns raw ids when no model is bound.
     *
     * @return list<mixed>
     */
    public function get(): array
    {
        $ids = $this->ids();
        if ($this->model === null || $ids === []) {
            return $ids;
        }

        /** @var class-string $model */
        $model = $this->model;
        $key   = $model::getPrimaryKeyName();
        // One query for all ids, then reorder to match the ranking.
        $found = $model::query()->whereIn($key, $ids)->get();

        $byId = [];
        foreach ($found as $m) {
            $byId[(string) $m->getAttribute($key)] = $m;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }
        return $ordered;
    }

    public function first(): mixed
    {
        return $this->get()[0] ?? null;
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->get());
    }
}
