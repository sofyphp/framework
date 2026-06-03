<?php

/** @var \Sofy\Http\Router $router */

use Main\Controllers\DocsController;
use Main\Controllers\HomeController;
use Main\Controllers\UiDemoController;

// Controller actions (not closures) so `php sofy route:cache` / `optimize`
// can serialize the routing table. See Main\Controllers\HomeController.
$router->get('/',              [HomeController::class, 'welcome']);
$router->get('/debug-error',   [HomeController::class, 'debugError']);
$router->get('/lang/{locale}', [HomeController::class, 'setLocale']);

$router->get('/ui-demo', [UiDemoController::class, 'index']);

$router->get('/docs',           [DocsController::class, 'index']);
$router->get('/docs/{section}', [DocsController::class, 'show']);
