<?php

declare(strict_types=1);

namespace Sofy\Cache;

class FileCache implements CacheInterface
{
    public function __construct(private readonly string $dir)
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->path($key);
        if (!file_exists($file)) return $default;

        $data = unserialize((string) file_get_contents($file));
        if ($data['ttl'] !== 0 && $data['ttl'] < time()) {
            unlink($file);
            return $default;
        }
        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return file_put_contents(
            $this->path($key),
            serialize(['ttl' => $ttl > 0 ? time() + $ttl : 0, 'value' => $value]),
            LOCK_EX
        ) !== false;
    }

    public function has(string $key): bool
    {
        $sentinel = "\x00__miss__\x00";
        return $this->get($key, $sentinel) !== $sentinel;
    }

    public function forget(string $key): bool
    {
        $file = $this->path($key);
        return !file_exists($file) || unlink($file);
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
        foreach (glob($this->dir . '/*.cache') ?: [] as $file) {
            unlink($file);
        }
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

    private function path(string $key): string
    {
        return $this->dir . '/' . md5($key) . '.cache';
    }
}
