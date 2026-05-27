<?php

declare(strict_types=1);

namespace Shop;

use Sofy\Core\Application;
use Sofy\Core\Module;

class Shop extends Module
{
    public function name(): string
    {
        return 'shop';
    }

    /**
     * Module configuration — accessible via config('shop.*').
     */
    public function config(): array
    {
        return require $this->path('config.php');
    }

    /**
     * Register services/bindings into the DI container.
     */
    public function register(Application $app): void
    {
        // $app->singleton(SomeService::class, fn() => new SomeService());
    }

    /**
     * Subscribe to events, register observers, model observers, etc.
     * Called after all modules are registered.
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
            // Commands\ShopCommand::class,
        ];
    }

    // Routes are defined in routes.php — no need to override routes() here.
}
