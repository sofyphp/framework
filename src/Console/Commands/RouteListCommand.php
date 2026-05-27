<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Closure;
use Sofy\Console\Command;
use Sofy\Core\Application;
use Sofy\Http\Route;

class RouteListCommand extends Command
{
    protected string $signature   = 'route:list';
    protected string $description = 'List all registered routes';

    public function handle(): int
    {
        $router = Application::getInstance()->router();
        $rows   = $router->getRoutes();

        if (empty($rows)) {
            $this->warn('No routes registered.');
            return 0;
        }

        // Deduplicate: one route object may appear under multiple methods
        $seen   = [];
        $unique = [];
        foreach ($rows as $row) {
            /** @var Route $route */
            $route = $row['route'];
            $key   = spl_object_id($route);
            if (!isset($seen[$key])) {
                $seen[$key]   = true;
                $unique[$key] = $route;
            }
        }

        $namedRoutes = $router->getNamedRoutes();
        $nameByRoute = [];
        foreach ($namedRoutes as $name => $route) {
            $nameByRoute[spl_object_id($route)] = $name;
        }

        $this->line();
        $this->line(sprintf(
            '  %-10s %-40s %-40s %s',
            'Method', 'URI', 'Action', 'Name'
        ));
        $this->line('  ' . str_repeat('─', 100));

        foreach ($unique as $id => $route) {
            $methods    = implode('|', $route->getMethods());
            $path       = $route->getPath();
            $action     = $this->formatAction($route->getAction());
            $name       = $nameByRoute[$id] ?? $route->getName() ?? '';
            $middleware = $route->getMiddleware();

            $this->line(sprintf(
                '  %-10s %-40s %-40s %s',
                $methods,
                $path,
                $action,
                $name
            ));

            if (!empty($middleware)) {
                $this->line(sprintf('  %-10s %-40s %s', '', '', implode(', ', $middleware)));
            }
        }

        $this->line();
        return 0;
    }

    private function formatAction(mixed $action): string
    {
        if ($action instanceof Closure) {
            return 'Closure';
        }
        if (is_string($action)) {
            return $action;
        }
        if (is_array($action) && count($action) === 2) {
            $class  = is_string($action[0]) ? $action[0] : get_class($action[0]);
            // Show short class name only
            $parts  = explode('\\', $class);
            return end($parts) . '@' . $action[1];
        }
        return '?';
    }
}
