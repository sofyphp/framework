<?php

declare(strict_types=1);

namespace Messenger\WebSocket;

use Sofy\WebSocket\WebSocketConnection;
use Sofy\WebSocket\WebSocketHandler;

/**
 * Relays "new message" signals between chat clients. It does NOT carry message
 * bodies — those are persisted and fetched over HTTP. The browser sends a tiny
 * bump after posting; this handler forwards it to everyone in the channel room,
 * who then fetch the new messages over HTTP. That gives instant delivery with
 * a running ws:serve and no Redis bridge.
 *
 *   php sofy ws:serve --handler="Messenger\WebSocket\ChatHandler"
 *
 * Client protocol (JSON):
 *   {"action":"join","room":"chat.5"}    — subscribe to a channel
 *   {"action":"bump","room":"chat.5"}    — tell the room to refetch
 */
class ChatHandler extends WebSocketHandler
{
    public function onMessage(WebSocketConnection $conn, string $message): void
    {
        $data = json_decode($message, true);
        if (!is_array($data)) {
            return;
        }

        $action = (string) ($data['action'] ?? '');
        $room   = (string) ($data['room'] ?? '');
        // Only chat.* rooms — never let a client address arbitrary rooms.
        if ($room === '' || !str_starts_with($room, 'chat.')) {
            return;
        }

        if ($action === 'join') {
            $conn->join($room);
            return;
        }

        if ($action === 'bump' && $conn->inRoom($room)) {
            // Notify everyone in the room (including the sender — harmless, it
            // just refetches and de-dupes by message id).
            $this->server->toJson($room, ['bump' => true, 'room' => $room]);
        }
    }
}
