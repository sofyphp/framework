<?php

declare(strict_types=1);

use Products\Controllers\Admin\ProductsController;
use Sofy\Admin\Middleware\EnsureAdmin;
use Sofy\Http\Router;

/** @var Router $router */

$router->group(['prefix' => 'admin/products', 'middleware' => [EnsureAdmin::class]], function (Router $router): void {
    $router->get ('/',           [ProductsController::class, 'index']);
    $router->get ('/create',     [ProductsController::class, 'create']);
    $router->post('/',           [ProductsController::class, 'store']);
    $router->get ('/{id}',       [ProductsController::class, 'show']);
    $router->get ('/{id}/edit',  [ProductsController::class, 'edit']);
    $router->post('/{id}',       [ProductsController::class, 'update']);
    $router->post('/{id}/delete',[ProductsController::class, 'destroy']);
});
