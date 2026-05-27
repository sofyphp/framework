<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class ViewClearCommand extends Command
{
    protected string $signature   = 'view:clear';
    protected string $description = 'Clear all compiled view files';

    public function handle(): int
    {
        $dir = function_exists('storage_path')
            ? storage_path('views')
            : (function_exists('base_path') ? base_path('storage/views') : 'storage/views');

        $files = glob($dir . '/*.php') ?: [];

        if (empty($files)) {
            $this->line('No compiled views to clear.');
            return 0;
        }

        foreach ($files as $file) {
            unlink($file);
        }

        $this->success('Compiled views cleared (' . count($files) . ' files removed).');
        return 0;
    }
}
