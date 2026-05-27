<?php

declare(strict_types=1);

namespace Sofy\Cache;

use Sofy\Redis\RedisClient;

/**
 * Multi-driver cache facade.
 *
 * Reads `cache.default` config to choose between 'file', 'redis', 'array'.
 * An L1 in-process memory layer sits above every driver — repeated reads
 * within the same request never hit the underlying store.
 *
 * Static proxy methods delegate to the resolved CacheInterface instance.
 */
class Cache
{
    /** @var array<string, CacheInterface> resolved driver instances */
    private static array $drivers = [];

    /**
     * L1 in-process memory layer.
     * Eliminates repeated driver reads when the same key is accessed more
     * than once per request.
     *
     * @var array<string, mixed>
     */
    private static array $memory = [];

    /**
     * Sentinel object used to distinguish a cached null/false from a cache miss.
     * Created once; compared by identity (not equality).
     */
    private static ?\stdClass $miss = null;

    // ── Driver resolution ─────────────────────────────────────────────────────

    private static function driver(?string $name = null): CacheInterface
    {
        $name ??= function_exists('config') ? (string) config('cache.default', 'file') : 'file';

        if (isset(self::$drivers[$name])) {
            return self::$drivers[$name];
        }

        $cfg = function_exists('config') ? (array) config("cache.drivers.$name", []) : [];
        $driver = $cfg['driver'] ?? $name;

        self::$drivers[$name] = match ($driver) {
            'redis' => self::makeRedis($cfg),
            'array' => new ArrayCache(),
            default => self::makeFile($cfg),
        };

        return self::$drivers[$name];
    }

    private static function makeFile(array $cfg): FileCache
    {
        $path = $cfg['path'] ?? (
            function_exists('base_path')
                ? base_path('storage/cache')
                : sys_get_temp_dir() . '/sofy_cache'
        );
        return new FileCache($path);
    }

    private static function makeRedis(array $cfg): RedisCache
    {
        $redisCfg = function_exists('config') ? (array) config('cache.redis', []) : [];
        $prefix   = $cfg['prefix'] ?? ($redisCfg['prefix'] ?? '');
        $client   = RedisClient::getInstance();
        return new RedisCache($client, $prefix);
    }

    // ── Public API ────────────────────────────────────────────────────────────

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$memory)) {
            return self::$memory[$key];
        }

        self::$miss ??= new \stdClass();
        $value = static::driver()->get($key, self::$miss);

        if ($value !== self::$miss) {
            return self::$memory[$key] = $value;
        }

        return $default;
    }

    public static function set(string $key, mixed $value, int $ttl = 0): bool
    {
        self::$memory[$key] = $value;
        return static::driver()->set($key, $value, $ttl);
    }

    public static function has(string $key): bool
    {
        if (array_key_exists($key, self::$memory)) {
            return true;
        }
        return static::driver()->has($key);
    }

    public static function forget(string $key): bool
    {
        unset(self::$memory[$key]);
        return static::driver()->forget($key);
    }

    public static function flush(): void
    {
        self::$memory = [];
        static::driver()->flush();
    }

    public static function increment(string $key, int $by = 1): int
    {
        $value = static::driver()->increment($key, $by);
        return self::$memory[$key] = $value;
    }

    public static function decrement(string $key, int $by = 1): int
    {
        $value = static::driver()->decrement($key, $by);
        return self::$memory[$key] = $value;
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        if (array_key_exists($key, self::$memory)) {
            return self::$memory[$key];
        }

        $value = static::driver()->remember($key, $ttl, $callback);
        return self::$memory[$key] = $value;
    }

    /** Switch to a named driver for all subsequent calls in this request. */
    public static function store(string $name): CacheInterface
    {
        return static::driver($name);
    }
}
