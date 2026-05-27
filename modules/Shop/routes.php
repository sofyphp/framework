<?php

declare(strict_types=1);

use Sofy\Http\Router;

/** @var Router $router */

// ── Web routes ────────────────────────────────────────────────────────────────

$router->web(function (Router $router): void {
    // $router->get('/shop',        [Controllers\ShopController::class, 'index']);
    // $router->get('/shop/{id}',   [Controllers\ShopController::class, 'show']);
    // $router->resource('shop',     Controllers\ShopController::class);
});

// ── API routes (auto-prefixed with /api) ──────────────────────────────────────

$router->api(function (Router $router): void {
    // $router->get('/shop',         [Controllers\Api\ShopController::class, 'index']);
    // $router->apiResource('shop',   Controllers\Api\ShopController::class);
});
