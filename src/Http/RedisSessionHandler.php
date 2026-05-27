<?php

declare(strict_types=1);

namespace Sofy\Http;

use Sofy\Redis\RedisClient;

/**
 * PHP session handler backed by Redis.
 * Register via session_set_save_handler() before session_start().
 */
class RedisSessionHandler implements \SessionHandlerInterface
{
    public function __construct(
        private readonly RedisClient $redis,
        private readonly int         $ttl    = 7200,
        private readonly string      $prefix = 'sofy_sess:',
    ) {}

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $data = $this->redis->get($this->prefix . $id);
        return $data === false ? '' : $data;
    }

    public function write(string $id, string $data): bool
    {
        return $this->redis->set($this->prefix . $id, $data, $this->ttl);
    }

    public function destroy(string $id): bool
    {
        $this->redis->del($this->prefix . $id);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        // Redis TTL handles expiry automatically — nothing to do.
        return 0;
    }
}
