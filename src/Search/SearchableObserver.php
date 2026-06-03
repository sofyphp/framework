<?php

declare(strict_types=1);

namespace Sofy\Search;

/**
 * Keeps the search index in step with a Searchable model's lifecycle. Wired
 * automatically by the Searchable trait. Fails soft: a search backend hiccup
 * (e.g. the index table not migrated yet) must never block a model save or
 * delete, so every call is guarded.
 */
final class SearchableObserver
{
    public function saved(object $model): void
    {
        try {
            Search::index($model);
        } catch (\Throwable) {
            // indexing is best-effort; never break the write path
        }
    }

    public function deleted(object $model): void
    {
        try {
            Search::unindex($model);
        } catch (\Throwable) {
        }
    }
}
