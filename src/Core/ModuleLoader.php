<?php

declare(strict_types=1);

namespace Sofy\Core;

use Sofy\Http\Router;

/**
 * ModuleLoader — discovers, registers, and boots all framework modules.
 *
 * Discovery convention:
 *   modules/{Name}/{Name}.php   →   class Modules\{Name}\{Name} extends Module
 *
 * The loader calls each lifecycle method in the correct order:
 *   1. discover()     — scan filesystem and instantiate Module objects
 *   2. registerAll()  — merge config + call Module::register() on each
 *   3. loadRoutes()   — call Module::routes() on each (web context only)
 *   4. bootAll()      — call Module::boot() on each
 */
class ModuleLoader
{
    /** @var Module[] */
    private array $modules = [];

    /** @return Module[]  Loaded modules in discovery order. */
    public function modules(): array
    {
        return $this->modules;
    }

    /**
     * Scan a directory for modules and instantiate them.
     * Expected layout: {path}/{Name}/{Name}.php with class Modules\{Name}\{Name}.
     *
     * Safe to call multiple times or on non-existent directories.
     */
    public function discover(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (glob($path . '/*/') ?: [] as $dir) {
            $name      = basename($dir);
            $classFile = $dir . $name . '.php';

            if (!file_exists($classFile)) {
                continue;
            }

            $class = $name . '\\' . $name;

            // Require the file only if the class hasn't been loaded yet
            if (!class_exists($class, autoload: false)) {
                require_once $classFile;
            }

            if (!class_exists($class)) {
                continue; // file exists but class name doesn't match convention
            }

            $module = new $class();

            if (!$module instanceof Module) {
                continue;
            }

            $this->modules[] = $module;
        }
    }

    /**
     * Merge each module's config into the app and call Module::register().
     * Call this before boot() so services are available during routing.
     */
    public function registerAll(Application $app): void
    {
        foreach ($this->modules as $module) {
            $config = $module->config();
            if ($config !== []) {
                $app->mergeConfig($module->name(), $config);
            }
            $module->register($app);
        }
    }

    /**
     * Call Module::routes() on every module.
     * Only invoked in web context (not in console boot).
     */
    public function loadRoutes(Router $router): void
    {
        foreach ($this->modules as $module) {
            $module->routes($router);
        }
    }

    /**
     * Call Module::boot() on every module.
     * Called after all modules are registered so cross-module dependencies work.
     */
    public function bootAll(Application $app): void
    {
        foreach ($this->modules as $module) {
            $module->boot($app);
        }
    }

    /**
     * Aggregate command class names from all modules.
     *
     * @return array<class-string>
     */
    public function allCommands(): array
    {
        if ($this->modules === []) {
            return [];
        }

        return array_merge(...array_map(
            fn(Module $m) => $m->commands(),
            $this->modules,
        ));
    }

    /**
     * @return Module[]
     */
    public function getModules(): array
    {
        return $this->modules;
    }
}
