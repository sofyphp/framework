<?php

/** @var \Sofy\Http\Router $router */

use Main\Controllers\DocsController;
use Main\Controllers\UiDemoController;
use Sofy\Http\Cookie;
use Sofy\Http\Request;
use Sofy\Http\Response;

$router->get('/', function (): Response {
    return view('welcome');
});

$router->get('/debug-error', function (): never {
    throw new RuntimeException('Test exception from Sofy debug page');
});

$router->get('/ui-demo', [UiDemoController::class, 'index']);

$router->get('/docs',          [DocsController::class, 'index']);
$router->get('/docs/{section}', [DocsController::class, 'show']);

$router->get('/lang/{locale}', function (Request $request, string $locale): Response {
    if (!in_array($locale, ['en', 'ru'], true)) {
        $locale = 'en';
    }
    Cookie::queue('app_locale', $locale, minutes: 60 * 24 * 365);
    $back = $_SERVER['HTTP_REFERER'] ?? '/';
    // Only redirect to same-origin URLs
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $parsed = parse_url($back);
    if (!empty($parsed['host']) && $parsed['host'] !== $host) {
        $back = '/';
    }
    return Response::redirect($back);
});
