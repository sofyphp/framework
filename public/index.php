<?php

declare(strict_types=1);

define('SOFY_START', microtime(true));

require_once dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$app->run();
