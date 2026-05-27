<?php

declare(strict_types=1);

namespace Sofy\Broadcasting\Drivers;

use Sofy\Broadcasting\BroadcastDriver;
use Sofy\Redis\RedisClient;

/**
 * Broadcasts events via Redis pub/sub.
 * The WebSocket server subscribes to the same channel and pushes to clients.
 *
 * Message format (JSON):
 *   {"event": "OrderShipped", "data": {...}}
 */
class RedisDriver implements BroadcastDriver
{
    public function broadcast(string $channel, string $event, array $data): void
    {
        $message = json_encode(['event' => $event, 'data' => $data], JSON_UNESCAPED_UNICODE);
        RedisClient::getInstance()->publish($channel, (string) $message);
    }
}
