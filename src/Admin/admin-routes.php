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
use Sofy\Admin\Controllers\DatabaseController;
use Sofy\Admin\Controllers\SystemController;
use Sofy\Admin\Controllers\UsersController;
use Sofy\Admin\Icons;
use Sofy\Admin\Middleware\EnsureAdmin;
use Sofy\Http\Router;

/** @var Router $router */

$router->group(['prefix' => 'admin', 'middleware' => [EnsureAdmin::class]], function (Router $router): void {
    $router->get('/',      [DashboardController::class, 'index']);
    $router->get('/users', [UsersController::class,     'index']);

    // ── System info ─────────────────────────────────────────────────────────
    $router->get('/system',         [SystemController::class, 'overview']);
    $router->get('/system/modules', [SystemController::class, 'modules']);

    // ── Database browser ────────────────────────────────────────────────────
    $router->get('/database',               [DatabaseController::class, 'index']);
    $router->get('/database/sql',           [DatabaseController::class, 'sqlConsole']);
    $router->post('/database/sql',          [DatabaseController::class, 'runSql']);
    $router->get('/database/table/{table}', [DatabaseController::class, 'show']);
});

// Stock menu items. Modules add more via Admin::menu()->add(...) from their
// Module::register() — those registrations run before this file loads, so
// module items are already in the panel by the time the chrome renders.
Admin::menu()->add('users', 'Users', '/admin/users')
    ->icon(Icons::USERS)->section('Manage')->order(10);

Admin::menu()->add('system',         'Overview',    '/admin/system')
    ->icon(Icons::SETTINGS)->section('System')->order(10);
Admin::menu()->add('system.modules', 'Modules',     '/admin/system/modules')
    ->icon(Icons::BOX)->section('System')->order(20);
Admin::menu()->add('database',       'Database',    '/admin/database')
    ->icon(Icons::DATABASE)->section('System')->order(30);
Admin::menu()->add('database.sql',   'SQL Console', '/admin/database/sql')
    ->icon(Icons::TERMINAL)->section('System')->order(40);
