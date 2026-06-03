<?php

declare(strict_types=1);

use Demo\Controllers\DemoController;
use Sofy\Http\Router;

/** @var Router $router */

// Controller actions (not closures) keep the module cacheable — see
// `php sofy route:cache`. One closure route would disable the whole cache.

// ── Web routes ────────────────────────────────────────────────────────────────

$router->web(function (Router $router): void {
    $router->get('/demo',      [DemoController::class, 'greeting']);
    $router->get('/demo/info', [DemoController::class, 'info']);
});

// ── API routes (auto-prefixed with /api) ──────────────────────────────────────

$router->api(function (Router $router): void {
    $router->get('/demo', [DemoController::class, 'api']);
});
