<?php

declare(strict_types=1);

/*
 | Test bootstrap. Loads the autoloader and constructs an Application so the
 | global helpers (config(), auth(), session()) resolve during unit tests.
 | A fresh in-memory SQLite connection is wired per-test by TestCase.
*/

require __DIR__ . '/../vendor/autoload.php';

$_ENV['APP_KEY']   = 'base64:' . base64_encode(random_bytes(32));
$_ENV['APP_URL']   = 'http://localhost';
$_ENV['APP_ENV']   = 'testing';
$_ENV['APP_DEBUG'] = 'false';
putenv('APP_URL=http://localhost');

// Construct the application so Application::getInstance() works for helpers.
// Restore PHPUnit's error handler afterwards — Application installs one that
// turns notices into exceptions, which would fight the test runner.
$app = new \Sofy\Core\Application(dirname(__DIR__));
restore_error_handler();
restore_exception_handler();
