<?php

declare(strict_types=1);

namespace Sofy\Search\Drivers;

/**
 * Ephemeral in-memory inverted index. Holds everything in a PHP array for the
 * duration of the request — nothing is persisted. Useful for tests and for
 * SEARCH_DRIVER=collection on tiny datasets. Component one-shot ranking goes
 * through Engine::rank() instead, which doesn't need a stored index at all.
 */
final class CollectionDriver implements DriverInterface
{
    /** @var array<string, array<string, array<string,float>>>  [index][docId][term] => weight */
    private array $store = [];

    public function put(string $index, string $docId, array $terms): void
    {
        $this->store[$index][$docId] = $terms;
    }

    public function remove(string $index, string $docId): void
    {
        unset($this->store[$index][$docId]);
    }

    public function flush(?string $index = null): void
    {
        if ($index === null) {
            $this->store = [];
        } else {
            unset($this->store[$index]);
        }
    }

    public function query(string $index, array $terms, ?string $prefix, int $limit, int $offset): array
    {
        $scores = [];
        foreach ($this->store[$index] ?? [] as $docId => $docTerms) {
            $score = 0.0;
            foreach ($terms as $term) {
                $score += $docTerms[$term] ?? 0.0;
            }
            if ($prefix !== null && $prefix !== '') {
                foreach ($docTerms as $term => $w) {
                    // Numeric term keys arrive as PHP ints — cast before matching.
                    if (str_starts_with((string) $term, $prefix)) {
                        $score += $w;
                        break; // count the prefix once per doc
                    }
                }
            }
            if ($score > 0.0) {
                $scores[$docId] = $score;
            }
        }

        arsort($scores);
        return array_slice($scores, $offset, $limit, true);
    }
}
