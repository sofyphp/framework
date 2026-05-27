<?php

declare(strict_types=1);

namespace Sofy\Cache;

/**
 * Volatile in-process cache — lives only for the duration of the request.
 * Useful for tests and environments without a persistent cache backend.
 */
class ArrayCache implements CacheInterface
{
    /** @var array<string, array{value: mixed, expires: int}> */
    private array $store = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->store[$key])) return $default;

        $item = $this->store[$key];
        if ($item['expires'] !== 0 && $item['expires'] < time()) {
            unset($this->store[$key]);
            return $default;
        }
        return $item['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $this->store[$key] = [
            'value'   => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
        ];
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key, "\x00__miss__\x00") !== "\x00__miss__\x00";
    }

    public function forget(string $key): bool
    {
        unset($this->store[$key]);
        return true;
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
        $this->store = [];
    }

    public function increment(string $key, int $by = 1): int
    {
        $value = (int) $this->get($key, 0) + $by;
        $this->set($key, $value);
        return $value;
    }

    public function decrement(string $key, int $by = 1): int
    {
        return $this->increment($key, -$by);
    }
}
