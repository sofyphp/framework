<?php

declare(strict_types=1);

namespace Sofy\Search;

/**
 * Make a model searchable. Add `use Searchable;` and the model auto-(re)indexes
 * on save and drops from the index on delete (via SearchableObserver, wired by
 * bootSearchable()).
 *
 * Customize what gets indexed by overriding toSearchableArray(); declare field
 * weights in config('search.indexes')[Model::class]['fields'].
 *
 *   class Product extends Model {
 *       use Searchable;
 *       public function toSearchableArray(): array {
 *           return ['sku' => $this->sku, 'name' => $this->name];
 *       }
 *   }
 *
 *   Product::search('red router')->get();   // ranked Product models
 */
trait Searchable
{
    /** Wire the auto-indexing observer when the model boots. */
    public static function bootSearchable(): void
    {
        static::observe(SearchableObserver::class);
    }

    /**
     * The fields to index. Defaults to the full attribute array; override to
     * index only specific fields (recommended).
     *
     * @return array<string,mixed>
     */
    public function toSearchableArray(): array
    {
        return $this->toArray();
    }

    /** The index name this model lives under (its class by default). */
    public static function searchableIndex(): string
    {
        return static::class;
    }

    /** The document key used in the index (primary key by default). */
    public function searchableKey(): string
    {
        return (string) $this->getPrimaryKeyValue();
    }

    /** Convenience: Product::search('term')->get() */
    public static function search(string $query, int $limit = 20, int $offset = 0): SearchResult
    {
        return Search::query(static::searchableIndex(), $query, $limit, $offset);
    }

    /** Index (or re-index) every row of this model. Returns the count. */
    public static function makeAllSearchable(): int
    {
        return Search::import(static::class);
    }
}
