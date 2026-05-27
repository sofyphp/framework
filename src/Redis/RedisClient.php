<?php

declare(strict_types=1);

namespace Sofy\Redis;

/**
 * Lazy-connecting singleton wrapper around ext-redis (\Redis).
 *
 * Provides typed proxy methods for the most common Redis commands
 * (strings, hashes, lists, sets, sorted sets, pub/sub, pipelines).
 *
 * Usage:
 *   $r = RedisClient::getInstance();
 *   $r->set('key', 'value', 60);
 *   $r->get('key');
 *
 * Connection is established on the first call — not on instantiation.
 *
 * Config keys (read from config('cache.redis')):
 *   host, port, password, database, timeout, prefix
 */
class RedisClient
{
    private static ?self $instance = null;

    private \Redis $redis;
    private bool   $connected = false;
    private array  $config;

    private function __construct(array $config)
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException(
                'ext-redis is not installed. Run: pecl install redis  or  apt-get install php-redis'
            );
        }
        $this->config = $config;
        $this->redis  = new \Redis();
    }

    // ── Singleton ─────────────────────────────────────────────────────────────

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            $config = function_exists('config')
                ? (array) config('cache.redis', [])
                : [];
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /** Replace the singleton (useful in tests / different connection configs). */
    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    // ── Connection ────────────────────────────────────────────────────────────

    private function connect(): void
    {
        if ($this->connected) {
            return;
        }

        $host     = (string)  ($this->config['host']     ?? '127.0.0.1');
        $port     = (int)     ($this->config['port']     ?? 6379);
        $timeout  = (float)   ($this->config['timeout']  ?? 2.0);
        $password = ($this->config['password'] ?? null) ?: null;
        $db       = (int)     ($this->config['database'] ?? 0);

        $this->redis->connect($host, $port, $timeout);

        if ($password !== null) {
            $this->redis->auth($password);
        }

        if ($db !== 0) {
            $this->redis->select($db);
        }

        $this->connected = true;
    }

    /** Raw \Redis instance (use sparingly — prefer typed proxy methods). */
    public function raw(): \Redis
    {
        $this->connect();
        return $this->redis;
    }

    // ── Strings ───────────────────────────────────────────────────────────────

    public function get(string $key): string|false
    {
        $this->connect();
        return $this->redis->get($key);
    }

    public function set(string $key, string $value, int $ttl = 0): bool
    {
        $this->connect();
        return $ttl > 0
            ? (bool) $this->redis->setex($key, $ttl, $value)
            : (bool) $this->redis->set($key, $value);
    }

    public function del(string ...$keys): int
    {
        $this->connect();
        return (int) $this->redis->del(...$keys);
    }

    public function exists(string $key): bool
    {
        $this->connect();
        return (bool) $this->redis->exists($key);
    }

    public function expire(string $key, int $ttl): bool
    {
        $this->connect();
        return (bool) $this->redis->expire($key, $ttl);
    }

    public function ttl(string $key): int
    {
        $this->connect();
        return (int) $this->redis->ttl($key);
    }

    public function incr(string $key, int $by = 1): int
    {
        $this->connect();
        return $by === 1
            ? (int) $this->redis->incr($key)
            : (int) $this->redis->incrBy($key, $by);
    }

    public function decr(string $key, int $by = 1): int
    {
        $this->connect();
        return $by === 1
            ? (int) $this->redis->decr($key)
            : (int) $this->redis->decrBy($key, $by);
    }

    public function keys(string $pattern): array
    {
        $this->connect();
        return $this->redis->keys($pattern) ?: [];
    }

    // ── Hashes ────────────────────────────────────────────────────────────────

    public function hget(string $key, string $field): string|false
    {
        $this->connect();
        return $this->redis->hGet($key, $field);
    }

    public function hset(string $key, string $field, string $value): int|false
    {
        $this->connect();
        return $this->redis->hSet($key, $field, $value);
    }

    public function hmset(string $key, array $fields): bool
    {
        $this->connect();
        return (bool) $this->redis->hMSet($key, $fields);
    }

    public function hmget(string $key, array $fields): array
    {
        $this->connect();
        return $this->redis->hMGet($key, $fields) ?: [];
    }

    public function hgetall(string $key): array
    {
        $this->connect();
        return $this->redis->hGetAll($key) ?: [];
    }

    public function hdel(string $key, string ...$fields): int
    {
        $this->connect();
        return (int) $this->redis->hDel($key, ...$fields);
    }

    public function hexists(string $key, string $field): bool
    {
        $this->connect();
        return (bool) $this->redis->hExists($key, $field);
    }

    // ── Lists ─────────────────────────────────────────────────────────────────

    public function lpush(string $key, string ...$values): int|false
    {
        $this->connect();
        return $this->redis->lPush($key, ...$values);
    }

    public function rpush(string $key, string ...$values): int|false
    {
        $this->connect();
        return $this->redis->rPush($key, ...$values);
    }

    public function lpop(string $key): string|false
    {
        $this->connect();
        return $this->redis->lPop($key);
    }

    public function rpop(string $key): string|false
    {
        $this->connect();
        return $this->redis->rPop($key);
    }

    public function llen(string $key): int
    {
        $this->connect();
        return (int) $this->redis->lLen($key);
    }

    public function lrange(string $key, int $start, int $end): array
    {
        $this->connect();
        return $this->redis->lRange($key, $start, $end) ?: [];
    }

    // ── Sets ──────────────────────────────────────────────────────────────────

    public function sadd(string $key, string ...$values): int
    {
        $this->connect();
        return (int) $this->redis->sAdd($key, ...$values);
    }

    public function srem(string $key, string ...$values): int
    {
        $this->connect();
        return (int) $this->redis->sRem($key, ...$values);
    }

    public function smembers(string $key): array
    {
        $this->connect();
        return $this->redis->sMembers($key) ?: [];
    }

    public function sismember(string $key, string $value): bool
    {
        $this->connect();
        return (bool) $this->redis->sIsMember($key, $value);
    }

    public function scard(string $key): int
    {
        $this->connect();
        return (int) $this->redis->sCard($key);
    }

    // ── Sorted Sets ───────────────────────────────────────────────────────────

    public function zadd(string $key, float $score, string $member): int
    {
        $this->connect();
        return (int) $this->redis->zAdd($key, $score, $member);
    }

    public function zrange(string $key, int $start, int $end, bool $withScores = false): array
    {
        $this->connect();
        return $this->redis->zRange($key, $start, $end, $withScores) ?: [];
    }

    public function zrevrange(string $key, int $start, int $end, bool $withScores = false): array
    {
        $this->connect();
        return $this->redis->zRevRange($key, $start, $end, $withScores) ?: [];
    }

    public function zrank(string $key, string $member): int|false
    {
        $this->connect();
        return $this->redis->zRank($key, $member);
    }

    public function zscore(string $key, string $member): float|false
    {
        $this->connect();
        return $this->redis->zScore($key, $member);
    }

    public function zrem(string $key, string ...$members): int
    {
        $this->connect();
        return (int) $this->redis->zRem($key, ...$members);
    }

    public function zcard(string $key): int
    {
        $this->connect();
        return (int) $this->redis->zCard($key);
    }

    public function zrangebyscore(string $key, string $min, string $max, array $options = []): array
    {
        $this->connect();
        return $this->redis->zRangeByScore($key, $min, $max, $options) ?: [];
    }

    public function zremrangebyscore(string $key, string $min, string $max): int
    {
        $this->connect();
        return (int) $this->redis->zRemRangeByScore($key, $min, $max);
    }

    // ── Pub / Sub ─────────────────────────────────────────────────────────────

    public function publish(string $channel, string $message): int
    {
        $this->connect();
        return (int) $this->redis->publish($channel, $message);
    }

    /**
     * Subscribe to channels — blocks until the callback returns false.
     * Runs in a loop; use a dedicated process or Swoole coroutine.
     */
    public function subscribe(array $channels, callable $callback): void
    {
        $this->connect();
        $this->redis->subscribe($channels, $callback);
    }

    // ── Pipeline / Transaction ────────────────────────────────────────────────

    /**
     * Execute a pipeline — all commands are sent in one round-trip.
     *
     * Usage:
     *   $results = $redis->pipeline(function (\Redis $pipe) {
     *       $pipe->set('a', 1);
     *       $pipe->set('b', 2);
     *   });
     */
    public function pipeline(callable $callback): array
    {
        $this->connect();
        $this->redis->multi(\Redis::PIPELINE);
        $callback($this->redis);
        return $this->redis->exec() ?: [];
    }

    /**
     * Execute an MULTI/EXEC transaction.
     *
     * Usage:
     *   $results = $redis->transaction(function (\Redis $tx) {
     *       $tx->incr('counter');
     *       $tx->set('flag', '1');
     *   });
     */
    public function transaction(callable $callback): array
    {
        $this->connect();
        $this->redis->multi(\Redis::MULTI);
        $callback($this->redis);
        return $this->redis->exec() ?: [];
    }

    // ── Misc ──────────────────────────────────────────────────────────────────

    public function ping(): bool
    {
        $this->connect();
        return $this->redis->ping() === true || $this->redis->ping() === '+PONG';
    }

    public function flushDb(): bool
    {
        $this->connect();
        return (bool) $this->redis->flushDB();
    }
}