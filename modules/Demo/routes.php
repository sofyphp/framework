<?php

declare(strict_types=1);

use Sofy\Http\Router;

/** @var Router $router */

// ── Web routes ────────────────────────────────────────────────────────────────

$router->web(function (Router $router): void {
    $router->get('/demo', fn() => config('demo.greeting', 'Hello!'));

    $router->get('/demo/info', fn() => view('demo::info', [
        'greeting' => config('demo.greeting'),
        'version'  => config('demo.version'),
    ]));
});

// ── API routes (auto-prefixed with /api) ──────────────────────────────────────

$router->api(function (Router $router): void {
    $router->get('/demo', fn() => [
        'module'   => 'demo',
        'greeting' => config('demo.greeting'),
        'version'  => config('demo.version'),
    ]);
});
