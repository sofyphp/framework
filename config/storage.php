<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Disk
    |--------------------------------------------------------------------------
    | Which disk to use when calling Storage::put(...) / Storage::get(...)
    | without specifying a disk explicitly.
    |
    | Supported: "local", "public", or any key defined in "disks" below.
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Disk Configurations
    |--------------------------------------------------------------------------
    | Each disk has a "driver" key ("local" or "s3") plus driver-specific
    | options.  Add as many disks as you need — they are resolved lazily.
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => function_exists('base_path')
                ? base_path('storage/app')
                : sys_get_temp_dir() . '/sofy/app',
        ],

        'public' => [
            'driver' => 'local',
            'root'   => function_exists('base_path')
                ? base_path('public/storage')
                : sys_get_temp_dir() . '/sofy/public',
            'url'    => env('APP_URL', 'http://localhost') . '/storage',
        ],

        's3' => [
            'driver'   => 's3',
            'key'      => env('AWS_ACCESS_KEY_ID',     ''),
            'secret'   => env('AWS_SECRET_ACCESS_KEY', ''),
            'region'   => env('AWS_DEFAULT_REGION',    'us-east-1'),
            'bucket'   => env('AWS_BUCKET',            ''),
            'endpoint' => env('AWS_ENDPOINT',          ''), // empty = AWS
            'url'      => env('AWS_URL',               ''), // public base URL
        ],

    ],

];
