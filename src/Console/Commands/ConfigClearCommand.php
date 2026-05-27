<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class ConfigClearCommand extends Command
{
    protected string $signature   = 'config:clear';
    protected string $description = 'Remove the configuration cache file';

    public function handle(): int
    {
        $file = function_exists('base_path') ? base_path('bootstrap/cache/config.php') : 'bootstrap/cache/config.php';

        if (!file_exists($file)) {
            $this->line('Configuration cache not found.');
            return 0;
        }

        unlink($file);
        $this->success('Configuration cache cleared.');
        return 0;
    }
}
