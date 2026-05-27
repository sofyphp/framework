<?php

declare(strict_types=1);

namespace Sofy\Cache;

use Sofy\Redis\RedisClient;

class RedisCache implements CacheInterface
{
    private string $prefix;

    public function __construct(private readonly RedisClient $redis, string $prefix = '')
    {
        $this->prefix = $prefix ? rtrim($prefix, ':') . ':' : '';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $raw = $this->redis->get($this->k($key));
        if ($raw === false) return $default;
        return unserialize($raw);
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return $this->redis->set($this->k($key), serialize($value), $ttl);
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($this->k($key));
    }

    public function forget(string $key): bool
    {
        return $this->redis->del($this->k($key)) >= 0;
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);
        if ($value !== null) return $value;
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    public function flush(): void
    {
        $this->redis->flushDb();
    }

    public function increment(string $key, int $by = 1): int
    {
        return $this->redis->incr($this->k($key), $by);
    }

    public function decrement(string $key, int $by = 1): int
    {
        return $this->redis->decr($this->k($key), $by);
    }

    private function k(string $key): string
    {
        return $this->prefix . $key;
    }
}
