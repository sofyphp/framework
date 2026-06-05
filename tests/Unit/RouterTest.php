<?php

declare(strict_types=1);

namespace Tests\Unit;

use Sofy\Http\Router;
use Tests\TestCase;

final class RouterTest extends TestCase
{
    public function test_static_and_dynamic_matching(): void
    {
        $r = new Router();
        $r->get('/users', ['C', 'index']);
        $r->get('/users/{id}', ['C', 'show']);

        $routes = $r->getRoutes();
        $this->assertNotEmpty($routes);

        $match = static function (Router $r, string $path): ?array {
            foreach ($r->getRoutes() as $e) {
                if ($e['method'] === 'GET' && $e['route']->matches($path)) {
                    return $e['route']->extractParams($path);
                }
            }
            return null;
        };

        $this->assertSame([], $match($r, '/users'));
        $this->assertSame(['id' => '42'], $match($r, '/users/42'));
        $this->assertNull($match($r, '/nope'));
    }

    public function test_cache_state_roundtrip(): void
    {
        $r = new Router();
        $r->get('/a', ['C', 'a']);
        $r->get('/b/{x}', ['C', 'b']);

        $state = $r->cacheState();
        $this->assertArrayHasKey('routes', $state);

        $r2 = new Router();
        $r2->restoreState($state);
        $found = false;
        foreach ($r2->getRoutes() as $e) {
            if ($e['route']->matches('/b/7')) {
                $found = true;
                $this->assertSame(['x' => '7'], $e['route']->extractParams('/b/7'));
            }
        }
        $this->assertTrue($found, 'restored route should match');
    }

    public function test_closure_routes_are_uncacheable(): void
    {
        $r = new Router();
        $r->get('/closure', fn() => 'hi');
        $this->assertNotEmpty($r->uncacheableRoutes());
        $this->expectException(\RuntimeException::class);
        $r->cacheState();
    }

    public function test_global_middleware_dedupes(): void
    {
        $r = new Router();
        $r->globalMiddleware(['A', 'B']);
        $r->globalMiddleware(['B', 'C']);
        $this->assertSame(['A', 'B', 'C'], $r->getGlobalMiddleware());
    }
}
