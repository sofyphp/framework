<?php

return [
    /*
     * Session driver: 'file' | 'redis'
     */
    'driver'   => env('SESSION_DRIVER',   'file'),

    /*
     * Session lifetime in minutes.
     */
    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    /*
     * Cookie name.
     */
    'cookie'   => env('SESSION_COOKIE', 'sofy_session'),

    /*
     * Redis key prefix for sessions (driver=redis only).
     */
    'prefix'   => env('SESSION_PREFIX', 'sofy_sess:'),

    /*
     * Redis database index for sessions (overrides cache.redis.database when set).
     * null → uses cache.redis.database
     */
    'redis_db' => env('SESSION_REDIS_DB') !== null ? (int) env('SESSION_REDIS_DB') : null,
];
