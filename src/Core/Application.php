<?php

declare(strict_types=1);

namespace Sofy\Core;

use Sofy\Database\Connection;
use Sofy\Http\Request;
use Sofy\Http\Response;
use Sofy\Http\Router;
use Sofy\Support\Dotenv;
use Throwable;

class Application
{
    private static Application $instance;

    /** Lazily-read framework version from composer.json. @see version() */
    private static ?string $cachedVersion = null;

    /**
     * Sofy framework version — single source of truth is the `version` field
     * in composer.json at the project root. Bumped together with the git tag
     * for a release; `php sofy update` reads this to compare against the
     * latest stable on Packagist.
     *
     * Returns 'dev' if composer.json is missing or has no version field.
     */
    public static function version(): string
    {
        if (self::$cachedVersion !== null) {
            return self::$cachedVersion;
        }
        $path = dirname(__DIR__, 2) . '/composer.json';
        if (is_file($path)) {
            $data = json_decode((string) file_get_contents($path), true);
            if (is_array($data) && isset($data['version']) && is_string($data['version'])) {
                return self::$cachedVersion = $data['version'];
            }
        }
        return self::$cachedVersion = 'dev';
    }
    private Container $container;
    private ModuleLoader $moduleLoader;
    private string $basePath;
    private array $configCache   = [];
    private array $errorHandlers = [];

    /**
     * In-memory cache for error view file contents.
     * null  = file not found; string = file contents.
     * Eliminates file_exists + file_get_contents on every error response.
     *
     * @var array<int, string|null>
     */
    private array $errorViewCache = [];

    public function __construct(string $basePath)
    {
        $this->basePath     = rtrim($basePath, '/');
        $this->container    = new Container();
        $this->moduleLoader = new ModuleLoader();
        $this->container->instance(Application::class, $this);
        static::$instance = $this;

        $this->loadEnvironment();
        // Wire global error handling BEFORE we touch modules/routes/DB. Without
        // this, fatal errors during loadModules()/boot() (e.g. a missing PSR-4
        // class in a freshly-dropped module) bubble up to PHP's default handler
        // and the operator sees a blank Nginx 500 instead of the debug page.
        $this->registerErrorHandlers();
        $this->registerCoreBindings();
        $this->loadCachedConfig();
    }

    /**
     * Warm the config cache from bootstrap/cache/config.php when present.
     * `php sofy config:cache` writes every config/*.php into one pre-merged
     * array with env() already resolved — so a production request does zero
     * config-file requires and zero per-file stat() calls.
     *
     * Note: env() values are baked in at cache time. Re-run config:cache (or
     * `php sofy optimize`) after editing .env. `php sofy optimize:clear`
     * removes the cache and restores live per-file loading.
     */
    private function loadCachedConfig(): void
    {
        $cacheFile = $this->basePath('bootstrap/cache/config.php');
        if (!is_file($cacheFile)) {
            return;
        }
        $cached = require $cacheFile;
        if (is_array($cached)) {
            // Module configs injected later via mergeConfig() still merge on
            // top — this only pre-seeds the base config/ files.
            $this->configCache = $cached + $this->configCache;
        }
    }

    public static function getInstance(): static
    {
        return static::$instance;
    }

    private function loadEnvironment(): void
    {
        Dotenv::loadSafe($this->basePath . '/.env');
    }

    private function registerCoreBindings(): void
    {
        $this->container->singleton(Router::class, fn() => new Router());
    }

    /**
     * Install three layers of error capture so any uncaught throwable —
     * during bootstrap, during routing, or as a PHP fatal — ends up rendered
     * through renderException() and the operator sees the same debug page
     * regardless of WHERE the failure happened.
     *
     *   1. set_error_handler        — PHP notices/warnings → ErrorException
     *   2. set_exception_handler    — uncaught throwables outside handle()
     *      (boot crashes, register() class-not-found, module wiring fails)
     *   3. register_shutdown_function — E_ERROR / E_PARSE / E_CORE_ERROR that
     *      no userland handler can normally intercept.
     */
    private function registerErrorHandlers(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            // Respect @ suppression and the error_reporting mask.
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (\Throwable $e): void {
            $this->emitErrorResponse($e);
        });

        register_shutdown_function(function (): void {
            $err = error_get_last();
            if ($err === null) return;
            $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING];
            if (!in_array($err['type'], $fatal, true)) return;

            $e = new \ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']);
            $this->emitErrorResponse($e);
        });
    }

    /**
     * Send an error response from a global handler context — i.e. when PHP
     * has already left the Application::handle() try/catch and may have
     * partially streamed output. Clears any open output buffers, falls back
     * to a minimal plain-text page if renderException() itself fails so the
     * browser at least sees a useful message instead of nothing.
     */
    private function emitErrorResponse(\Throwable $e): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        try {
            $this->renderException($e)->send();
            return;
        } catch (\Throwable $inner) {
            if (!headers_sent()) {
                header('HTTP/1.1 500 Internal Server Error');
                header('Content-Type: text/plain; charset=utf-8');
            }
            $debug = $this->env('APP_DEBUG', 'false') === 'true';
            if ($debug) {
                echo "Sofy: uncaught {$this->shortClass($e)}: {$e->getMessage()}\n";
                echo '  at ' . $e->getFile() . ':' . $e->getLine() . "\n\n";
                echo "While handling this, the renderer itself failed:\n";
                echo '  ' . $this->shortClass($inner) . ': ' . $inner->getMessage() . "\n";
                echo '  at ' . $inner->getFile() . ':' . $inner->getLine() . "\n";
            } else {
                echo "Internal Server Error\n";
            }
        }
    }

    private function shortClass(\Throwable $e): string
    {
        $name  = get_class($e);
        $parts = explode('\\', $name);
        return (string) end($parts);
    }

    /**
     * Discover and register all modules under {basePath}/modules/ (or a custom path).
     * Must be called before boot() so modules can bind services before routing.
     */
    public function loadModules(?string $path = null): static
    {
        $path ??= $this->basePath('modules');
        $this->moduleLoader->discover($path);
        $this->moduleLoader->registerAll($this);
        return $this;
    }

    /**
     * Merge an array into the config cache under the given top-level key.
     * Used by ModuleLoader to inject module configs into the global config system.
     */
    public function mergeConfig(string $key, array $config): void
    {
        $existing = $this->configCache[$key] ?? [];
        $this->configCache[$key] = array_merge(
            is_array($existing) ? $existing : [],
            $config,
        );
    }

    public function getModuleLoader(): ModuleLoader
    {
        return $this->moduleLoader;
    }

    public function boot(): static
    {
        $this->bootDatabase();
        $this->bootHttpMiddleware();
        // Fast path: restore the pre-built routing table (with its compiled
        // regexes) from bootstrap/cache/routes.php instead of requiring every
        // routes.php and re-constructing ~80 Route objects per request.
        if (!$this->loadRoutesFromCache()) {
            $this->buildRoutes();
        }
        $this->moduleLoader->bootAll($this);
        return $this;
    }

    /**
     * Build the routing table from scratch: app routes (web/api/admin) plus
     * every module's routes(). This is the un-cached path and also what
     * `php sofy route:cache` invokes to produce the snapshot.
     */
    public function buildRoutes(): void
    {
        $this->loadRoutes();
        $this->moduleLoader->loadRoutes($this->router());
    }

    /**
     * Restore routes from bootstrap/cache/routes.php if it exists and is
     * valid. Returns true when the cache was applied (so boot() skips the
     * fresh build entirely), false to fall through to buildRoutes().
     */
    private function loadRoutesFromCache(): bool
    {
        $cacheFile = $this->basePath('bootstrap/cache/routes.php');
        if (!is_file($cacheFile)) {
            return false;
        }
        try {
            $state = require $cacheFile;
        } catch (Throwable) {
            return false; // corrupt cache — rebuild live rather than 500
        }
        if (!is_array($state) || !isset($state['routes'])) {
            return false;
        }
        $this->router()->restoreState($state);
        return true;
    }

    public function bootForConsole(): static
    {
        $this->bootDatabase();
        $this->moduleLoader->bootAll($this);
        return $this;
    }

    private function bootDatabase(): void
    {
        $config = $this->config('database');
        if (!$config) {
            return;
        }
        try {
            $connection = new Connection($config);
            Connection::setDefault($connection);
            $this->container->instance(Connection::class, $connection);
        } catch (Throwable) {
            // DB not available — skip silently
        }
    }

    /**
     * Install the framework's default global HTTP middleware stack.
     * Runs once during boot, before any routes load — ensures
     * SecurityHeaders / CsrfMiddleware / CorsMiddleware see every
     * request including the admin chrome and module routes.
     *
     * Override per-app: call `app(Router::class)->globalMiddleware([...])`
     * in bootstrap/app.php to append more, or replace the wiring entirely
     * by setting `Application::$autoSecurityMiddleware = false;` before
     * `$app->boot()`.
     */
    public static bool $autoSecurityMiddleware = true;

    private function bootHttpMiddleware(): void
    {
        if (!static::$autoSecurityMiddleware) {
            return;
        }
        $router = $this->container->make(Router::class);
        $router->globalMiddleware([
            \Sofy\Http\Middleware\SecurityHeaders::class,
            \Sofy\Http\Middleware\CorsMiddleware::class,
            \Sofy\Http\Middleware\CsrfMiddleware::class,
        ]);
    }

    private function loadRoutes(): void
    {
        $router = $this->container->make(Router::class);

        $web = $this->basePath('routes/web.php');
        if (file_exists($web)) {
            $router->load($web);
        }

        $api = $this->basePath('routes/api.php');
        if (file_exists($api)) {
            $router->group(['prefix' => 'api'], function (Router $r) use ($api) {
                $r->load($api);
            });
        }

        // Built-in admin panel — registers /admin/* routes and the stock menu
        // items. Loaded *after* the app's own routes so an application can
        // override anything by registering the same URL in routes/web.php.
        // Modules contribute extra admin pages and menu items in their own
        // routes.php / Module::register(), independently of this file.
        $admin = __DIR__ . '/../Admin/admin-routes.php';
        if (file_exists($admin)) {
            $router->load($admin);
        }
    }

    public function run(): void
    {
        $request  = Request::capture();
        $response = $this->handle($request);
        $response->send();
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->container->make(Router::class)->dispatch($request);
        } catch (Throwable $e) {
            return $this->renderException($e);
        }
    }

    /**
     * Register a custom handler for a specific HTTP error status.
     *
     * Usage in bootstrap/app.php:
     *   $app->error(404, fn(Throwable $e) => response('Custom 404', 404));
     *   $app->error(500, fn(Throwable $e) => view('errors.my500', ['e' => $e], 500));
     */
    public function error(int $status, callable $handler): void
    {
        $this->errorHandlers[$status] = $handler;
    }

    private function renderException(Throwable $e): Response
    {
        $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

        // 1. Custom handler registered via $app->error(N, fn) — always wins
        if (isset($this->errorHandlers[$status])) {
            try {
                return $this->errorHandlers[$status]($e, $status);
            } catch (Throwable) {
                // handler itself crashed — fall through to defaults
            }
        }

        // 2. Debug page — only for real crashes, never for intentional HTTP errors
        $isHttpException = $e instanceof \Sofy\Http\HttpException;
        if (!$isHttpException && $this->env('APP_DEBUG', 'false') === 'true') {
            $body = (new ExceptionHandler())->render($e);
            return (new Response($body, 500))->withHeader('Content-Type', 'text/html; charset=utf-8');
        }

        // 3. Static view file resources/views/errors/{status}.php (cached in memory)
        if (!array_key_exists($status, $this->errorViewCache)) {
            $viewFile = $this->basePath("resources/views/errors/$status.php");
            $this->errorViewCache[$status] = file_exists($viewFile)
                ? (string) file_get_contents($viewFile)
                : null;
        }

        if ($this->errorViewCache[$status] !== null) {
            return new Response($this->errorViewCache[$status], $status);
        }

        return new Response('Internal Server Error', $status);
    }

    public function get(string $abstract): mixed
    {
        return $this->container->make($abstract);
    }

    public function bind(string $abstract, callable $factory): void
    {
        $this->container->bind($abstract, $factory);
    }

    public function singleton(string $abstract, callable $factory): void
    {
        $this->container->singleton($abstract, $factory);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        // Support dot notation: 'database.host' → config/database.php → ['host']
        $parts    = explode('.', $key);
        $fileKey  = array_shift($parts);

        if (!array_key_exists($fileKey, $this->configCache)) {
            $file = $this->basePath("config/$fileKey.php");
            $this->configCache[$fileKey] = file_exists($file) ? require $file : null;
        }

        $value = $this->configCache[$fileKey];

        foreach ($parts as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value ?? $default;
    }

    public function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    public function router(): Router
    {
        return $this->container->make(Router::class);
    }

    public function container(): Container
    {
        return $this->container;
    }

    // Path helpers
    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? '/' . ltrim($path, '/') : '');
    }

    public function appPath(string $path = ''): string
    {
        return $this->basePath('app' . ($path ? "/$path" : ''));
    }

    public function configPath(string $path = ''): string
    {
        return $this->basePath('config' . ($path ? "/$path" : ''));
    }

    public function viewPath(string $path = ''): string
    {
        return $this->basePath('resources/views' . ($path ? "/$path" : ''));
    }

    public function storagePath(string $path = ''): string
    {
        return $this->basePath('storage' . ($path ? "/$path" : ''));
    }
}
