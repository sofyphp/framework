<?php

declare(strict_types=1);

namespace Sofy\Log;

class Log
{
    private static ?Logger $instance = null;

    private static function logger(): Logger
    {
        if (self::$instance === null) {
            $path = function_exists('base_path')
                ? base_path('storage/logs/sofy.log')
                : sys_get_temp_dir() . '/sofy.log';
            self::$instance = new Logger($path);
        }
        return self::$instance;
    }

    public static function debug(string $message, array $context = []): void    { static::logger()->debug($message, $context); }
    public static function info(string $message, array $context = []): void     { static::logger()->info($message, $context); }
    public static function warning(string $message, array $context = []): void  { static::logger()->warning($message, $context); }
    public static function error(string $message, array $context = []): void    { static::logger()->error($message, $context); }
    public static function critical(string $message, array $context = []): void { static::logger()->critical($message, $context); }
}
