<?php

declare(strict_types=1);

namespace Sofy\Search\Drivers;

/**
 * A search backend. The Engine tokenizes/weights text and hands drivers
 * already-computed term→weight maps, so drivers only store and rank — they
 * never tokenize. This keeps tokenizer config in one place and lets a driver
 * be a thin storage layer (a DB table, an in-memory array, …).
 */
interface DriverInterface
{
    /**
     * Store (or replace) a document's terms under an index.
     *
     * @param array<string,float> $terms  term => weight (field weight × frequency)
     */
    public function put(string $index, string $docId, array $terms): void;

    /** Remove a single document from an index. */
    public function remove(string $index, string $docId): void;

    /** Clear an entire index, or all indexes when $index is null. */
    public function flush(?string $index = null): void;

    /**
     * Rank documents matching the query terms.
     *
     * @param list<string> $terms   exact terms (all but possibly the last)
     * @param string|null  $prefix  optional last token to match as a prefix
     *                              (autocomplete: "rou" matches "router")
     * @return array<string,float>  docId => score, ordered by score desc
     */
    public function query(string $index, array $terms, ?string $prefix, int $limit, int $offset): array;
}
