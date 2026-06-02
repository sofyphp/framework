<?php

declare(strict_types=1);

namespace Sofy\Http;

use Sofy\Core\Application;
use Sofy\Http\Middleware\Pipeline;

class Router
{
    /** @var array<string, Route[]> Dynamic (parametrised) routes — linear scan */
    private array $routes = [];

    /**
     * Static routes index — O(1) lookup for paths that contain no {param}.
     * Built alongside $routes in addRoute().
     *
     * @var array<string, array<string, Route>>   [METHOD]['/exact/path'] => Route
     */
    private array $staticRoutes = [];

    private array $groupStack  = [];
    private array $namedRoutes = [];

    /**
     * Middleware classes that wrap EVERY route regardless of group.
     * Application::bootHttpMiddleware() pre-populates this with the
     * framework's security defaults (SecurityHeaders, CsrfMiddleware,
     * CorsMiddleware). Add your own via Router::globalMiddleware([]).
     *
     * @var list<string>
     */
    private array $globalMiddleware = [];

    // Registration methods
    public function get(string $path, array|string|callable $action): Route
    {
        return $this->addRoute('GET', $path, $action);
    }

    public function post(string $path, array|string|callable $action): Route
    {
        return $this->addRoute('POST', $path, $action);
    }

    public function put(string $path, array|string|callable $action): Route
    {
        return $this->addRoute('PUT', $path, $action);
    }

    public function patch(string $path, array|string|callable $action): Route
    {
        return $this->addRoute('PATCH', $path, $action);
    }

    public function delete(string $path, array|string|callable $action): Route
    {
        return $this->addRoute('DELETE', $path, $action);
    }

    public function options(string $path, array|string|callable $action): Route
    {
        return $this->addRoute('OPTIONS', $path, $action);
    }

    public function any(string $path, array|string|callable $action): Route
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $path, $action);
    }

    public function match(array $methods, string $path, array|string|callable $action): Route
    {
        return $this->addRoute(array_map('strtoupper', $methods), $path, $action);
    }

    // Resource routes (index, show, create, store, edit, update, destroy)
    public function resource(string $name, string $controller): void
    {
        $base = '/' . trim($name, '/');
        $this->get($base,                            [$controller, 'index']);
        $this->get($base . '/create',                [$controller, 'create']);
        $this->post($base,                           [$controller, 'store']);
        $this->get($base . '/{id}',                  [$controller, 'show']);
        $this->get($base . '/{id}/edit',             [$controller, 'edit']);
        $this->match(['PUT', 'PATCH'], $base . '/{id}', [$controller, 'update']);
        $this->delete($base . '/{id}',               [$controller, 'destroy']);
    }

    public function apiResource(string $name, string $controller): void
    {
        $base = '/' . trim($name, '/');
        $this->get($base,                            [$controller, 'index']);
        $this->post($base,                           [$controller, 'store']);
        $this->get($base . '/{id}',                  [$controller, 'show']);
        $this->match(['PUT', 'PATCH'], $base . '/{id}', [$controller, 'update']);
        $this->delete($base . '/{id}',               [$controller, 'destroy']);
    }

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    /**
     * Register web (non-API) routes inside the callback.
     * Purely a named wrapper around the plain router — no prefix is added.
     * Symmetrical counterpart to api() for a clean single-file layout.
     */
    public function web(callable $callback): void
    {
        $callback($this);
    }

    /**
     * Register API routes inside the callback.
     * All routes are automatically prefixed with /api.
     *
     * Usage in a module's routes.php:
     *   $router->api(function (Router $router): void {
     *       $router->get('/posts', [PostApiController::class, 'index']); // → GET /api/posts
     *   });
     */
    public function api(callable $callback): void
    {
        $this->group(['prefix' => 'api'], $callback);
    }

    public function load(string $file): void
    {
        $router = $this;
        require $file;
    }

    // Dispatch
    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path   = $request->path();

        if ($route = $this->findRoute($method, $path)) {
            return $this->runRoute($route, $request);
        }

        // HEAD falls back to GET
        if ($method === 'HEAD' && $route = $this->findRoute('GET', $path)) {
            $response = $this->runRoute($route, $request);
            return $response->withContent('');
        }

        throw new HttpException(404);
    }

    private function findRoute(string $method, string $path): ?Route
    {
        // O(1) — exact match for static paths (no {params})
        if (isset($this->staticRoutes[$method][$path])) {
            return $this->staticRoutes[$method][$path];
        }

        // O(n) — regex scan for dynamic routes only
        foreach ($this->routes[$method] ?? [] as $route) {
            if ($route->matches($path)) {
                return $route;
            }
        }
        return null;
    }

    private function runRoute(Route $route, Request $request): Response
    {
        $params = $route->extractParams($request->path());
        $request->setRouteParams($params);

        // Global middleware wraps OUTSIDE per-route middleware so things
        // like SecurityHeaders see the final Response, and CsrfMiddleware
        // gets a chance to reject early before route logic runs.
        $middleware = array_merge($this->globalMiddleware, $route->getMiddleware());

        return (new Pipeline())
            ->send($request)
            ->through($middleware)
            ->then(fn(Request $req) => $this->callAction($route->getAction(), $req, $params));
    }

    /**
     * Append middleware to the global stack. Idempotent — duplicate
     * registrations are de-duplicated so re-bootstrapping doesn't keep
     * piling up the same class.
     *
     * @param list<string>|string $middleware
     */
    public function globalMiddleware(array|string $middleware): void
    {
        foreach ((array) $middleware as $m) {
            if (!in_array($m, $this->globalMiddleware, true)) {
                $this->globalMiddleware[] = $m;
            }
        }
    }

    /** @return list<string> */
    public function getGlobalMiddleware(): array
    {
        return $this->globalMiddleware;
    }

    private function callAction(array|string|callable $action, Request $request, array $params): Response
    {
        // Closure
        if ($action instanceof \Closure) {
            return $this->toResponse($action($request, ...$params));
        }

        // "Controller@method" string
        if (is_string($action) && str_contains($action, '@')) {
            [$class, $method] = explode('@', $action, 2);
            $controller = Application::getInstance()->get($class);
            return $this->toResponse($controller->$method($request, ...$params));
        }

        // [ControllerClass::class, 'method'] array
        if (is_array($action) && count($action) === 2) {
            [$class, $method] = $action;
            $controller = is_string($class)
                ? Application::getInstance()->get($class)
                : $class;
            return $this->toResponse($controller->$method($request, ...$params));
        }

        throw new \InvalidArgumentException('Invalid route action.');
    }

    private function toResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result) || (is_object($result) && !($result instanceof \Stringable))) {
            return Response::json($result);
        }
        return new Response((string) $result);
    }

    // Named routes
    public function name(string $name, Route $route): void
    {
        $this->namedRoutes[$name] = $route;
    }

    public function route(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \RuntimeException("Named route [$name] not found.");
        }
        return $this->namedRoutes[$name]->buildUrl($params);
    }

    public function getRoutes(): array
    {
        $flat = [];
        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $route) {
                $flat[] = ['method' => $method, 'route' => $route];
            }
        }
        return $flat;
    }

    public function getNamedRoutes(): array
    {
        return $this->namedRoutes;
    }

    // Internals
    private function addRoute(string|array $methods, string $path, array|string|callable $action): Route
    {
        $methods    = (array) $methods;
        $path       = $this->buildPath($path);
        $middleware = $this->collectGroupMiddleware();

        $route  = new Route($methods, $path, $action, $middleware);
        $static = !str_contains($path, '{');

        foreach ($methods as $method) {
            $this->routes[$method][] = $route;

            if ($static) {
                $this->staticRoutes[$method][$path] = $route;
            }
        }

        return $route;
    }

    private function buildPath(string $path): string
    {
        $prefix = '';
        foreach ($this->groupStack as $group) {
            $prefix .= '/' . trim($group['prefix'] ?? '', '/');
        }
        return rtrim($prefix, '/') . '/' . ltrim($path, '/');
    }

    private function collectGroupMiddleware(): array
    {
        $middleware = [];
        foreach ($this->groupStack as $group) {
            $middleware = array_merge($middleware, $group['middleware'] ?? []);
        }
        return $middleware;
    }
}
