<?php

/** @var \Sofy\Http\Router $router */

use Sofy\Http\Request;
use Sofy\Http\Response;

// Все роуты здесь автоматически получают префикс /api
$router->get('/ping', function (Request $request): Response {
    return json_response(['status' => 'ok', 'time' => now()]);
});

// Пример API-ресурса:
// $router->apiResource('users', \Main\Controllers\Api\UserController::class);
