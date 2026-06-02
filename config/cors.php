<?php

/*
 * CORS config. Defaults to the app's own APP_URL — single-origin sites
 * never need this open. Set CORS_ALLOWED_ORIGINS to a comma-separated
 * list to allow more (e.g. an SPA on another domain). Set it to '*' only
 * if you understand the implications (cookies + creds + CSRF all change).
 *
 * Pre-v0.4.13 default was ['*'], which paired badly with the also-default
 * /admin being publicly readable. The audit caught this.
 */
return [
    'allowed_origins'      => array_values(array_filter(
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', (string) env('APP_URL', 'http://localhost')))),
        static fn(string $o): bool => $o !== '',
    )),
    'allowed_methods'      => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers'      => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'X-CSRF-Token'],
    'exposed_headers'      => [],
    'max_age'              => 86400,
    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),
];
