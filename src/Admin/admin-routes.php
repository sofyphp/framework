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
use Sofy\Admin\Controllers\AuthController;
use Sofy\Admin\Controllers\DashboardController;
use Sofy\Admin\Controllers\DatabaseController;
use Sofy\Admin\Controllers\MarketplaceController;
use Sofy\Admin\Controllers\NotificationsController;
use Sofy\Admin\Controllers\SystemController;
use Sofy\Admin\Controllers\UpdateController;
use Sofy\Admin\Controllers\UsersController;
use Sofy\Admin\Middleware\EnsureAdmin;
use Sofy\Admin\Widgets;
use Sofy\Http\Router;
use Sofy\View\Icons;

/** @var Router $router */

// Built-in login/logout — registered OUTSIDE the EnsureAdmin group so an
// unauthenticated user can actually reach the form. EnsureAdmin also
// whitelists these paths. Override by defining /admin/login in routes/web.php
// (loaded earlier, so it wins).
$router->get ('/admin/login',  [AuthController::class, 'showLogin']);
$router->post('/admin/login',  [AuthController::class, 'login']);
$router->post('/admin/logout', [AuthController::class, 'logout']);

$router->group(['prefix' => 'admin', 'middleware' => [EnsureAdmin::class]], function (Router $router): void {
    $router->get('/',      [DashboardController::class, 'index']);
    $router->get('/users', [UsersController::class,     'index']);

    // ── Browser notifications feed (powers sofyNotify desktop alerts) ────────
    $router->get ('/notifications/feed', [NotificationsController::class, 'feed']);
    $router->post('/notifications/seen', [NotificationsController::class, 'seen']);

    // ── System info ─────────────────────────────────────────────────────────
    $router->get('/system',         [SystemController::class, 'overview']);
    $router->get('/system/modules', [SystemController::class, 'modules']);

    // ── Updates ─────────────────────────────────────────────────────────────
    $router->get ('/system/update',                [UpdateController::class, 'index']);
    $router->post('/system/update/run',            [UpdateController::class, 'run']);
    $router->post('/system/update/refresh-notes', [UpdateController::class, 'refreshNotes']);

    // ── Marketplace ─────────────────────────────────────────────────────────
    $router->get ('/system/marketplace',                     [MarketplaceController::class, 'index']);
    $router->post('/system/marketplace/refresh',             [MarketplaceController::class, 'refresh']);
    $router->post('/system/marketplace/{slug}/install',      [MarketplaceController::class, 'install']);
    $router->post('/system/marketplace/{slug}/uninstall',    [MarketplaceController::class, 'uninstall']);

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
Admin::menu()->add('system.update',  'Updates',     '/admin/system/update')
    ->icon(Icons::DOWNLOAD)->section('System')->order(50);
Admin::menu()->add('system.marketplace', 'Marketplace', '/admin/system/marketplace')
    ->icon(Icons::SHOPPING_BAG)->section('System')->order(60);

// Stock dashboard widgets. Modules can replace any of these by calling
// Admin::widget(MyWidget::class) — order is governed by the widget's $order
// property, so a module widget with $order < 0 lands above Welcome.
Admin::widget(Widgets\WelcomeWidget::class);
Admin::widget(Widgets\UsersCountWidget::class);
Admin::widget(Widgets\DatabaseStatsWidget::class);
Admin::widget(Widgets\ModulesCountWidget::class);
Admin::widget(Widgets\PhpRuntimeWidget::class);
Admin::widget(Widgets\QuickActionsWidget::class);
Admin::widget(Widgets\SystemHealthWidget::class);
