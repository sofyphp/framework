<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Sofy\Search\Search;

/**
 * Clear the search index — one model's, or everything.
 *
 *   php sofy search:flush                         # all indexes
 *   php sofy search:flush "Products\Models\Product"
 */
class SearchFlushCommand extends Command
{
    protected string $signature   = 'search:flush {model? : Model class to flush (omit for all)}';
    protected string $description  = 'Remove documents from the search index';

    public function handle(): int
    {
        $model = (string) ($this->argument('model') ?? '');

        try {
            Search::flush($model !== '' ? $model : null);
        } catch (\Throwable $e) {
            $this->error('Flush failed: ' . $e->getMessage());
            return 1;
        }

        $this->success($model !== '' ? "Flushed index for {$model}." : 'Flushed all search indexes.');
        return 0;
    }
}
