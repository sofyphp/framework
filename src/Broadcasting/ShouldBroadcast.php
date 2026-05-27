<?php

declare(strict_types=1);

namespace Sofy\Broadcasting;

interface ShouldBroadcast
{
    /** Redis/WebSocket channel name, e.g. 'orders.42'. */
    public function broadcastOn(): string;

    /** Event name sent to the client, e.g. 'OrderShipped'. */
    public function broadcastAs(): string;

    /** Payload sent with the event. */
    public function broadcastWith(): array;
}
