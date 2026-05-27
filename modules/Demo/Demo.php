<?php

declare(strict_types=1);

namespace Demo;

use Sofy\Core\Application;
use Sofy\Core\Module;

/**
 * Demo module — minimal working example of the Sofy module system.
 *
 * Routes are defined in routes.php (auto-loaded by Module::routes()):
 *   Web: GET /demo
 *        GET /demo/info
 *   API: GET /api/demo
 */
class Demo extends Module
{
    public function name(): string
    {
        return 'demo';
    }

    public function config(): array
    {
        return require $this->path('config.php');
    }

    public function boot(Application $app): void
    {
        // Place for event listeners, observers, scheduled tasks, etc.
    }
}
