<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class ConfigCacheCommand extends Command
{
    protected string $signature   = 'config:cache';
    protected string $description = 'Create a cached config file for faster loading';

    public function handle(): int
    {
        $configDir  = function_exists('base_path') ? base_path('config') : 'config';
        $cacheFile  = function_exists('base_path') ? base_path('bootstrap/cache/config.php') : 'bootstrap/cache/config.php';
        $cacheDir   = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $merged = [];
        foreach (glob($configDir . '/*.php') ?: [] as $file) {
            $key          = basename($file, '.php');
            $merged[$key] = require $file;
        }

        $export = '<?php return ' . var_export($merged, true) . ';' . PHP_EOL;
        file_put_contents($cacheFile, $export);

        $this->success('Configuration cached successfully.');
        $this->line("Cache file: $cacheFile");
        return 0;
    }
}
