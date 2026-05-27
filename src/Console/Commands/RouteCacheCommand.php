<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Sofy\Core\Application;

class RouteCacheCommand extends Command
{
    protected string $signature   = 'route:cache';
    protected string $description = 'Create a route cache file for faster route registration';

    public function handle(): int
    {
        $cacheFile = function_exists('base_path') ? base_path('bootstrap/cache/routes.php') : 'bootstrap/cache/routes.php';
        $cacheDir  = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // Boot the app to collect all routes
        $app    = Application::getInstance();
        $router = $app->router();
        $routes = $router->getRoutes();

        $serialized = serialize($routes);
        $export     = '<?php return unserialize(' . var_export($serialized, true) . ');' . PHP_EOL;

        file_put_contents($cacheFile, $export);

        $this->success('Routes cached successfully (' . count($routes) . ' routes).');
        return 0;
    }
}
