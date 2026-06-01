<?php

declare(strict_types=1);

/**
 * Admin panel routes. Auto-loaded by Application::loadRoutes() after the
 * app's own routes/web.php so app-defined routes always win on a conflict.
 *
 * Modules contribute additional admin pages by registering them inside
 * their own routes.php — they don't need to touch this file.
 */

use Sofy\Admin\Admin;
use Sofy\Admin\Controllers\DashboardController;
use Sofy\Admin\Controllers\UsersController;
use Sofy\Admin\Middleware\EnsureAdmin;
use Sofy\Http\Router;

/** @var Router $router */

$router->group(['prefix' => 'admin', 'middleware' => [EnsureAdmin::class]], function (Router $router): void {
    $router->get('/',      [DashboardController::class, 'index']);
    $router->get('/users', [UsersController::class,     'index']);
});

// Stock menu items — modules can add more via Admin::menu()->add(...) from
// their Module::register() hooks (the registrations happen before this file
// is loaded, so module items will already be in the panel by the time the
// dashboard renders them).
Admin::menu()->add('users', 'Users', '/admin/users')
    ->icon('👥')->section('Manage')->order(10);
