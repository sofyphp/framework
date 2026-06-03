<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Sofy\Core\Application;

/**
 * Pre-build the routing table into bootstrap/cache/routes.php so production
 * requests skip requiring every routes.php and re-compiling ~80 route regexes.
 * Application::boot() reads this file via loadRoutesFromCache().
 *
 * Wired live since v0.5.0 — before that the command wrote a file nothing read.
 */
class RouteCacheCommand extends Command
{
    protected string $signature   = 'route:cache';
    protected string $description = 'Cache the routing table for faster per-request boot';

    public function handle(): int
    {
        $base      = function_exists('base_path') ? base_path() : (string) getcwd();
        $cacheFile = $base . '/bootstrap/cache/routes.php';
        $cacheDir  = dirname($cacheFile);

        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
            $this->error("Could not create cache directory: $cacheDir");
            return 1;
        }

        // Build a FRESH routing table. Remove any stale cache first so boot()
        // takes the real build path, not a restore-from-stale-cache path.
        @unlink($cacheFile);

        $app = new Application($base);
        $app->loadModules();
        $app->boot(); // cache file is gone → builds routes from scratch

        try {
            $state = $app->router()->cacheState();
        } catch (\Throwable $e) {
            $this->error('Cannot cache routes: ' . $e->getMessage());
            $this->line('Routes with Closure actions are not cacheable. Convert them to');
            $this->line("[Controller::class, 'method'] array actions, then retry.");
            return 1;
        }

        // Route objects carry pre-compiled regexes — serialize() preserves
        // them; var_export() would emit __set_state() calls Route can't honour.
        $payload = '<?php return unserialize(' . var_export(serialize($state), true) . ');' . PHP_EOL;
        if (file_put_contents($cacheFile, $payload) === false) {
            $this->error("Could not write cache file: $cacheFile");
            return 1;
        }

        $count = array_sum(array_map('count', $state['routes'] ?? []));
        $this->success("Routes cached ({$count} routes) → bootstrap/cache/routes.php");
        $this->line('Run `php sofy route:clear` after changing routes, or `php sofy optimize` to refresh all caches.');
        return 0;
    }
}
