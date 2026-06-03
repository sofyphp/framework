<?php

/** @var \Sofy\Http\Router $router */

use Main\Controllers\Api\PingController;

// Все роуты здесь автоматически получают префикс /api.
// Controller actions (not closures) keep the table cacheable — see
// `php sofy route:cache` / `php sofy optimize`.
$router->get('/ping', [PingController::class, 'index']);

// Пример API-ресурса:
// $router->apiResource('users', \Main\Controllers\Api\UserController::class);
