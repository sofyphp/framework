<?php

declare(strict_types=1);

namespace Sofy\Broadcasting;

use Sofy\Broadcasting\Drivers\LogDriver;
use Sofy\Broadcasting\Drivers\NullDriver;
use Sofy\Broadcasting\Drivers\RedisDriver;

/**
 * Event broadcasting facade.
 *
 * Publishes events to connected clients via Redis pub/sub.
 * The WebSocket server (or a separate consumer) subscribes to the same
 * Redis channel and forwards messages to browser clients.
 *
 * Usage:
 *   Broadcaster::broadcast('orders.1', 'OrderShipped', ['order_id' => 1]);
 *
 * Or via ShouldBroadcast:
 *   class OrderShipped implements ShouldBroadcast {
 *       public function broadcastOn(): string { return 'orders.' . $this->order->id; }
 *       public function broadcastAs(): string { return 'OrderShipped'; }
 *       public function broadcastWith(): array { return ['id' => $this->order->id]; }
 *   }
 *   Broadcaster::event(new OrderShipped($order));
 */
class Broadcaster
{
    private static ?BroadcastDriver $driver = null;

    public static function setDriver(BroadcastDriver $driver): void
    {
        self::$driver = $driver;
    }

    public static function getDriver(): BroadcastDriver
    {
        if (self::$driver === null) {
            self::$driver = self::makeDefault();
        }
        return self::$driver;
    }

    /** Publish a raw message to a channel. */
    public static function broadcast(string $channel, string $event, array $data = []): void
    {
        self::getDriver()->broadcast($channel, $event, $data);
    }

    /** Dispatch a ShouldBroadcast event. */
    public static function event(ShouldBroadcast $event): void
    {
        self::broadcast(
            $event->broadcastOn(),
            $event->broadcastAs(),
            $event->broadcastWith(),
        );
    }

    private static function makeDefault(): BroadcastDriver
    {
        $driver = function_exists('config') ? config('broadcasting.driver', 'null') : 'null';

        return match ($driver) {
            'redis' => new RedisDriver(),
            'log'   => new LogDriver(),
            default => new NullDriver(),
        };
    }
}
