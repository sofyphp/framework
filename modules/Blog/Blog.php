<?php

declare(strict_types=1);

namespace Blog;

use Sofy\Core\Application;
use Sofy\Core\Module;
use Sofy\Http\Router;

class Blog extends Module
{
    public function name(): string
    {
        return 'blog';
    }

    /**
     * Register services/bindings into the DI container.
     */
    public function register(Application $app): void
    {
        // $app->singleton(SomeService::class, fn() => new SomeService());
    }

    /**
     * Register HTTP routes for this module.
     */
    public function routes(Router $router): void
    {
        // $router->get('/blog', [BlogController::class, 'index']);
    }

    /**
     * Subscribe to events, register observers, etc.
     */
    public function boot(Application $app): void
    {
        //
    }

    /**
     * Console commands provided by this module.
     *
     * @return array<class-string>
     */
    public function commands(): array
    {
        return [
            // Commands\BlogCommand::class,
        ];
    }

    /**
     * Module configuration — accessible via config('blog.*').
     */
    public function config(): array
    {
        return require $this->path('config.php');
    }
}
