<?php

declare(strict_types=1);

namespace Sofy\Core;

use Sofy\Http\Router;
use Throwable;

/**
 * ModuleLoader — discovers, registers, and boots all framework modules.
 *
 * Discovery convention:
 *   modules/{Name}/{Name}.php   →   class {Name}\{Name} extends Module
 *
 * Enable-list:
 *   {basePath}/bootstrap/modules.php returns ['enabled' => ['Blog', …]].
 *   Only modules listed in 'enabled' actually load. Dropping a folder in
 *   modules/ DOES NOT auto-enable it — `php sofy module:install {Name}`
 *   has to be run first (or the marketplace's install action), which
 *   patches composer.json psr-4 and adds the module to the enable-list.
 *
 * Backward compat:
 *   If bootstrap/modules.php doesn't exist on first boot, the loader
 *   auto-creates it with every module folder currently present in
 *   modules/. So upgrading from <0.4.10 to 0.4.10+ doesn't disable
 *   anything — only NEWLY dropped folders need explicit install.
 *
 * Safety:
 *   Module::register() calls are individually try/catch'd so a single
 *   broken module logs its exception and gets skipped instead of taking
 *   down the entire framework boot.
 *
 * Lifecycle the loader drives:
 *   1. discover()     — scan filesystem, instantiate Module objects
 *   2. registerAll()  — merge config + call Module::register()  (safe)
 *   3. loadRoutes()   — call Module::routes()                   (safe)
 *   4. bootAll()      — call Module::boot()                     (safe)
 */
class ModuleLoader
{
    public const string REGISTRY_FILE = 'bootstrap/modules.php';

    /** @var Module[] */
    private array $modules = [];

    /** Folders discovered on disk regardless of enable-list. @var list<string> */
    private array $discovered = [];

    /** @var array<string, Throwable>  Module name → failure (load or register). */
    private array $failed = [];

    /** @var list<string>  Modules enabled in registry but missing PSR-4 in composer.json. */
    private array $uninstalled = [];

    /** Resolved base path of the project — set the first time discover() runs. */
    private ?string $basePath = null;

    // ── Inspection ───────────────────────────────────────────────────────────

    /** @return Module[]  Loaded modules in discovery order. */
    public function modules(): array
    {
        return $this->modules;
    }

    /** @return Module[]  Alias kept for code written against the older API. */
    public function getModules(): array
    {
        return $this->modules;
    }

    /** @return list<string>  Every folder under modules/ that looks like a module. */
    public function discoverable(): array
    {
        return $this->discovered;
    }

    /** @return list<string>  Discoverable folders that ARE NOT in the enable-list. */
    public function disabled(): array
    {
        $enabledMap = array_flip($this->loadRegistry()['enabled'] ?? []);
        return array_values(array_filter(
            $this->discovered,
            static fn(string $name): bool => !isset($enabledMap[$name]),
        ));
    }

    /** @return array<string, Throwable>  Modules that errored during load or register(). */
    public function failed(): array
    {
        return $this->failed;
    }

    /**
     * @return list<string>  Modules that ARE in the enable-list but whose
     * PSR-4 namespace is NOT in composer.json — they were never properly
     * installed. The loader skips them before register() so they can't crash
     * boot; the admin UI surfaces them with a copy-pasteable install command.
     */
    public function uninstalled(): array
    {
        return $this->uninstalled;
    }

    // ── Lifecycle ────────────────────────────────────────────────────────────

    /**
     * Scan a directory for modules and instantiate the ones in the enable-list.
     * Safe to call multiple times or on non-existent directories.
     */
    public function discover(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        if ($this->basePath === null) {
            // basePath = parent of the modules dir, used to anchor the registry path.
            $this->basePath = dirname(rtrim($path, '/'));
        }

        // Inventory every candidate folder (one entry per name) so the admin can
        // show "discoverable but not yet installed" entries alongside enabled ones.
        $candidates = [];
        foreach (glob($path . '/*/') ?: [] as $dir) {
            $name      = basename($dir);
            $classFile = $dir . $name . '.php';
            if (file_exists($classFile)) {
                $candidates[$name] = $dir;
            }
        }
        $this->discovered = array_values(array_unique(array_merge($this->discovered, array_keys($candidates))));

        $registry = $this->loadRegistry();

        // First-run bootstrap: no registry file yet → adopt the modules that
        // are ALREADY in composer.json's psr-4 map (i.e. properly installed
        // before the upgrade). Modules whose folder is present but namespace
        // isn't registered get left in the "discovered but not enabled"
        // pile — auto-enabling them would just crash boot on the very next
        // request (their internal classes can't autoload).
        if ($registry === null) {
            $autoEnable = array_values(array_filter(
                array_keys($candidates),
                fn(string $name): bool => $this->isNamespaceRegistered($name),
            ));
            $registry = ['enabled' => $autoEnable];
            $this->saveRegistry($registry);
            $skipped = count($candidates) - count($autoEnable);
            error_log('[sofy] First boot: auto-enabled ' . count($autoEnable) . ' modules → ' . $this->registryPath()
                . ($skipped > 0 ? " (skipped {$skipped} folder(s) lacking composer.json psr-4)" : ''));
        }

        $enabled = array_flip($registry['enabled'] ?? []);

        foreach ($candidates as $name => $dir) {
            if (!isset($enabled[$name])) {
                continue; // discoverable but not installed — skip silently
            }
            // Pre-flight: if composer doesn't know the module's namespace,
            // it can't autoload widget/controller/model classes referenced
            // inside register(). Quarantine the module BEFORE invoking it so
            // a half-installed module never produces a 'Class X not found'
            // failure — show it on /admin/system/modules with a clear hint.
            if (!$this->isNamespaceRegistered($name)) {
                $this->uninstalled[] = $name;
                error_log("[sofy] Module {$name}: enabled in registry but PSR-4 not registered in composer.json. Run `php sofy module:install {$name}`.");
                continue;
            }
            $this->tryLoadModule($name, $dir);
        }
    }

    /**
     * Merge each module's config into the app and call Module::register().
     * Failures are isolated — a broken module is dropped from the active set
     * and recorded in $failed; the rest of the boot keeps running.
     */
    public function registerAll(Application $app): void
    {
        $survivors = [];
        foreach ($this->modules as $module) {
            $name = $this->shortName($module);
            try {
                $config = $module->config();
                if ($config !== []) {
                    $app->mergeConfig($module->name(), $config);
                }
                $module->register($app);
                $survivors[] = $module;
            } catch (Throwable $e) {
                $this->failed[$name] = $e;
                error_log("[sofy] Module {$name}::register() failed: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            }
        }
        $this->modules = $survivors;
    }

    /**
     * Call Module::routes() on every surviving module.
     * Only invoked in web context (not during console boot).
     */
    public function loadRoutes(Router $router): void
    {
        $survivors = [];
        foreach ($this->modules as $module) {
            $name = $this->shortName($module);
            try {
                $module->routes($router);
                $survivors[] = $module;
            } catch (Throwable $e) {
                $this->failed[$name] = $e;
                error_log("[sofy] Module {$name}::routes() failed: {$e->getMessage()}");
            }
        }
        $this->modules = $survivors;
    }

    /**
     * Call Module::boot() on every module.
     * Called after register/routes so cross-module dependencies work.
     */
    public function bootAll(Application $app): void
    {
        $survivors = [];
        foreach ($this->modules as $module) {
            $name = $this->shortName($module);
            try {
                $module->boot($app);
                $survivors[] = $module;
            } catch (Throwable $e) {
                $this->failed[$name] = $e;
                error_log("[sofy] Module {$name}::boot() failed: {$e->getMessage()}");
            }
        }
        $this->modules = $survivors;
    }

    /**
     * Aggregate command class names from all modules. Failed modules don't
     * contribute commands (their commands() would just blow up the same way).
     *
     * @return array<class-string>
     */
    public function allCommands(): array
    {
        if ($this->modules === []) return [];
        $out = [];
        foreach ($this->modules as $m) {
            try {
                $out = array_merge($out, $m->commands());
            } catch (Throwable $e) {
                $this->failed[$this->shortName($m)] = $e;
            }
        }
        return $out;
    }

    // ── Registry management ──────────────────────────────────────────────────

    /**
     * Add a module name to the enable-list. Called by `php sofy module:install`
     * and the marketplace installer once the module is fully wired
     * (PSR-4 patched, dump-autoload done) so the next boot picks it up.
     */
    public function enable(string $name): bool
    {
        $registry = $this->loadRegistry() ?? ['enabled' => []];
        $enabled  = (array) ($registry['enabled'] ?? []);
        if (in_array($name, $enabled, true)) {
            return false; // already there
        }
        $enabled[] = $name;
        sort($enabled);
        $registry['enabled'] = $enabled;
        return $this->saveRegistry($registry);
    }

    /** Remove a module name from the enable-list. */
    public function disable(string $name): bool
    {
        $registry = $this->loadRegistry();
        if ($registry === null) return false;
        $enabled = (array) ($registry['enabled'] ?? []);
        if (!in_array($name, $enabled, true)) return false;
        $registry['enabled'] = array_values(array_filter($enabled, static fn(string $n): bool => $n !== $name));
        return $this->saveRegistry($registry);
    }

    public function isEnabled(string $name): bool
    {
        $enabled = (array) ($this->loadRegistry()['enabled'] ?? []);
        return in_array($name, $enabled, true);
    }

    public function registryPath(): string
    {
        return ($this->basePath ?? (string) (function_exists('base_path') ? base_path() : getcwd())) . '/' . self::REGISTRY_FILE;
    }

    /** @return array{enabled: list<string>}|null  null means the file is missing. */
    private function loadRegistry(): ?array
    {
        $path = $this->registryPath();
        if (!is_file($path)) return null;
        /** @noinspection PhpIncludeInspection */
        $data = require $path;
        if (!is_array($data) || !isset($data['enabled']) || !is_array($data['enabled'])) {
            return ['enabled' => []];
        }
        return ['enabled' => array_values(array_filter(array_map('strval', $data['enabled'])))];
    }

    /** @param array{enabled: list<string>} $registry */
    private function saveRegistry(array $registry): bool
    {
        $path = $this->registryPath();
        $dir  = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $body = "<?php\n\ndeclare(strict_types=1);\n\n/**\n * Module enable-list. Maintained by `php sofy module:install` and the\n * /admin/system/marketplace install button. Edit by hand to enable or\n * disable modules without re-running install.\n */\n\nreturn [\n    'enabled' => [\n"
            . implode('', array_map(static fn(string $n) => "        " . var_export($n, true) . ",\n", $registry['enabled']))
            . "    ],\n];\n";
        return @file_put_contents($path, $body) !== false;
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /**
     * Cheap composer.json lookup: is the module's top-level namespace already
     * mapped to a folder in psr-4? Doesn't validate that the folder is the
     * RIGHT one — just that an entry exists. Used as a pre-flight gate to
     * avoid invoking register() on a module whose internal classes can't
     * autoload.
     */
    private function isNamespaceRegistered(string $moduleName): bool
    {
        $composerPath = ($this->basePath ?? (string) (function_exists('base_path') ? base_path() : getcwd())) . '/composer.json';
        if (!is_file($composerPath)) {
            return true; // no composer.json — assume OK and let the loader try
        }
        $data = json_decode((string) file_get_contents($composerPath), true);
        if (!is_array($data)) return true;
        $psr4 = $data['autoload']['psr-4'] ?? [];
        return is_array($psr4) && isset($psr4[$moduleName . '\\']);
    }

    private function tryLoadModule(string $name, string $dir): void
    {
        $classFile = $dir . $name . '.php';
        try {
            $class = $name . '\\' . $name;
            if (!class_exists($class, autoload: false)) {
                /** @noinspection PhpIncludeInspection */
                require_once $classFile;
            }
            if (!class_exists($class)) {
                throw new \RuntimeException("Class {$class} not found after require {$classFile} — module folder, class name and namespace must all match.");
            }
            $module = new $class();
            if (!$module instanceof Module) {
                throw new \RuntimeException("Class {$class} does not extend " . Module::class . '.');
            }
            $this->modules[] = $module;
        } catch (Throwable $e) {
            $this->failed[$name] = $e;
            error_log("[sofy] Module {$name} failed to load: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    private function shortName(Module $m): string
    {
        $cls = get_class($m);
        $pos = strrpos($cls, '\\');
        return $pos === false ? $cls : substr($cls, $pos + 1);
    }
}
