<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Sofy\Search\Search;

/**
 * Bulk (re)index every row of a Searchable model into the search index.
 *
 *   php sofy search:import "Products\Models\Product"
 */
class SearchImportCommand extends Command
{
    protected string $signature   = 'search:import {model : Fully-qualified model class} {--fresh : Flush the index first}';
    protected string $description  = 'Index all rows of a model into the search engine';

    public function handle(): int
    {
        $model = (string) $this->argument('model');
        if ($model === '' || !class_exists($model)) {
            $this->error("Model class not found: {$model}");
            $this->line('Pass the fully-qualified name, e.g. "Products\\\\Models\\\\Product".');
            return 1;
        }

        if ($this->option('fresh')) {
            Search::flush($model);
            $this->line("Flushed existing index for {$model}.");
        }

        try {
            $count = Search::import($model);
        } catch (\Throwable $e) {
            $this->error('Import failed: ' . $e->getMessage());
            $this->line('Did you run `php sofy migrate` to create the search_index table?');
            return 1;
        }

        $this->success("Indexed {$count} document(s) from {$model}.");
        return 0;
    }
}
