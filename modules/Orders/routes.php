<?php

declare(strict_types=1);

/**
 * Orders module routes — auto-loaded by Module::routes() from the module
 * directory. All endpoints live under /admin and are protected by
 * EnsureAdmin so the module respects whatever auth policy the host app
 * decided (Admin::useAuth() / role checks).
 */

use Orders\Controllers\Admin\OrdersController;
use Sofy\Admin\Middleware\EnsureAdmin;
use Sofy\Http\Router;

/** @var Router $router */

$router->group(['prefix' => 'admin/orders', 'middleware' => [EnsureAdmin::class]], function (Router $router): void {
    $router->get ('/',              [OrdersController::class, 'index']);
    $router->get ('/create',        [OrdersController::class, 'create']);
    $router->post('/',              [OrdersController::class, 'store']);
    $router->get ('/{id}',          [OrdersController::class, 'show']);
    $router->get ('/{id}/edit',     [OrdersController::class, 'edit']);
    $router->post('/{id}',          [OrdersController::class, 'update']);
    $router->post('/{id}/status',   [OrdersController::class, 'updateStatus']);
    $router->post('/{id}/delete',   [OrdersController::class, 'destroy']);
});
