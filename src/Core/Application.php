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
        $this->registerCoreBindings();
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
        $this->loadRoutes();
        $this->moduleLoader->loadRoutes($this->router());
        $this->moduleLoader->bootAll($this);
        return $this;
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
