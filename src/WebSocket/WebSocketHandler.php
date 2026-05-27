<?php

declare(strict_types=1);

namespace Sofy\WebSocket;

/**
 * Base class for WebSocket message handlers.
 *
 * Extend this class and override the event methods you care about.
 * The $server property is injected before the first connection arrives,
 * so you can call broadcast/room helpers from any method.
 *
 * Usage:
 *   class ChatHandler extends WebSocketHandler
 *   {
 *       public function onOpen(WebSocketConnection $conn): void
 *       {
 *           $conn->join('lobby');
 *           $this->server->to('lobby', "User {$conn->id} joined.");
 *       }
 *
 *       public function onMessage(WebSocketConnection $conn, string $message): void
 *       {
 *           $this->server->to('lobby', $message, exceptId: $conn->id);
 *       }
 *
 *       public function onClose(WebSocketConnection $conn): void
 *       {
 *           $this->server->to('lobby', "User {$conn->id} left.");
 *       }
 *   }
 */
abstract class WebSocketHandler
{
    protected WebSocketServer $server;

    /** @internal injected by WebSocketServer before run() */
    public function setServer(WebSocketServer $server): void
    {
        $this->server = $server;
    }

    public function onOpen(WebSocketConnection $conn): void {}

    public function onMessage(WebSocketConnection $conn, string $message): void {}

    public function onBinary(WebSocketConnection $conn, string $data): void {}

    public function onClose(WebSocketConnection $conn): void {}

    public function onError(WebSocketConnection $conn, \Throwable $e): void
    {
        // Default: silently close
        $conn->close(1011, $e->getMessage());
    }

    /** Called when a ping frame is received (server auto-replies with pong). */
    public function onPing(WebSocketConnection $conn): void {}
}
