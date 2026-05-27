<?php

return [
    /*
     * Default cache driver: 'file' | 'redis' | 'array'
     */
    'default' => env('CACHE_DRIVER', 'file'),

    'drivers' => [

        'file' => [
            'driver' => 'file',
            'path'   => storage_path('cache'),
        ],

        'redis' => [
            'driver'   => 'redis',
            'prefix'   => env('CACHE_PREFIX', 'sofy_cache:'),
        ],

        'array' => [
            'driver' => 'array',
        ],
    ],

    /*
     * Redis connection settings (also used by Session when SESSION_DRIVER=redis).
     */
    'redis' => [
        'host'     => env('REDIS_HOST',     '127.0.0.1'),
        'port'     => (int) env('REDIS_PORT',     6379),
        'password' => env('REDIS_PASSWORD'),
        'database' => (int) env('REDIS_DB',       0),
        'timeout'  => (float) env('REDIS_TIMEOUT', 2.0),
    ],
];
