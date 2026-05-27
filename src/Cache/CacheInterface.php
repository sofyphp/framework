<?php

declare(strict_types=1);

namespace Sofy\Cache;

interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value, int $ttl = 0): bool;

    public function has(string $key): bool;

    public function forget(string $key): bool;

    /** Get a cached value or compute, cache, and return it. */
    public function remember(string $key, int $ttl, callable $callback): mixed;

    public function flush(): void;

    public function increment(string $key, int $by = 1): int;

    public function decrement(string $key, int $by = 1): int;
}