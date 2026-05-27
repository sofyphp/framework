<?php

declare(strict_types=1);

namespace Sofy\Core;

use Sofy\Http\Router;

/**
 * Module — base class for all Sofy modules.
 *
 * Each module lives in its own directory under modules/:
 *
 *   modules/
 *     Blog/
 *       Blog.php          ← class Blog extends Module
 *       config.php        ← returns array
 *       Controllers/
 *       Models/
 *       Commands/
 *       Views/
 *       Migrations/
 *
 * Lifecycle (called by ModuleLoader):
 *   1. register($app)  — bind services into the container (before routes / boot)
 *   2. routes($router) — register HTTP routes (web context only)
 *   3. boot($app)      — subscribe to events, set up observers, etc.
 */
abstract class Module
{
    /**
     * Unique module slug used as the config key and log prefix.
     * Keep it short and lowercase: 'blog', 'shop', 'admin'.
     */
    abstract public function name(): string;

    /**
     * Register services/bindings into the DI container.
     * Called before routes and boot — ideal for singleton registrations.
     */
    public function register(Application $app): void {}

    /**
     * Register HTTP routes for the module.
     * Only called in web context (not during console boot).
     *
     * Default implementation auto-loads routes.php from the module directory
     * with $router in scope. Override this method for inline route registration.
     *
     * In routes.php use $router->web(...) and $router->api(...):
     *
     *   $router->web(function (Router $router): void {
     *       $router->get('/blog', [BlogController::class, 'index']);
     *   });
     *
     *   $router->api(function (Router $router): void {
     *       $router->get('/blog', [BlogApiController::class, 'index']); // → GET /api/blog
     *   });
     */
    public function routes(Router $router): void
    {
        $file = $this->path('routes.php');
        if (file_exists($file)) {
            (static function () use ($file, $router): void {
                require $file;
            })();
        }
    }

    /**
     * Boot the module after all modules have been registered.
     * Good place for event listeners, model observers, scheduled tasks.
     */
    public function boot(Application $app): void {}

    /**
     * Return the console command class names provided by this module.
     *
     * @return array<class-string>
     */
    public function commands(): array
    {
        return [];
    }

    /**
     * Return the module's configuration array.
     * Merged into the global config under config('{name}.*').
     */
    public function config(): array
    {
        return [];
    }

    /**
     * Called once by `module:install` when the module is first set up.
     * Use this to seed initial data, create storage directories, publish assets, etc.
     */
    public function install(Application $app): void {}

    /**
     * Called by `module:uninstall` before the module directory is removed.
     * Use this to clean up storage, remove published assets, etc.
     */
    public function uninstall(Application $app): void {}

    /**
     * Resolve a path relative to the module's own directory.
     *
     *   $this->path()                     → /abs/path/to/modules/Blog
     *   $this->path('Views/posts')        → /abs/path/to/modules/Blog/Views/posts
     *   $this->path('Migrations')         → /abs/path/to/modules/Blog/Migrations
     */
    public function path(string $sub = ''): string
    {
        $dir = dirname((new \ReflectionClass($this))->getFileName());
        return $sub !== '' ? $dir . '/' . ltrim($sub, '/') : $dir;
    }
}
