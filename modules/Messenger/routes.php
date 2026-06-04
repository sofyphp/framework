<?php

declare(strict_types=1);

use Messenger\Controllers\Admin\MessagesController;
use Sofy\Admin\Middleware\EnsureAdmin;
use Sofy\Http\Router;

/** @var Router $router */

// All under /admin behind the admin gate. Controller actions (cacheable).
$router->group(['prefix' => 'admin', 'middleware' => [EnsureAdmin::class]], function (Router $router): void {
    $router->get ('/messages',                 [MessagesController::class, 'index']);
    $router->post('/messages/start-direct',    [MessagesController::class, 'startDirect']);
    $router->post('/messages/start-group',     [MessagesController::class, 'startGroup']);
    $router->get ('/messages/{id}',            [MessagesController::class, 'show']);
    $router->post('/messages/{id}/send',       [MessagesController::class, 'send']);
    $router->get ('/messages/{id}/poll',       [MessagesController::class, 'poll']);
});
