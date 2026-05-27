<?php

declare(strict_types=1);

namespace Sofy\Broadcasting\Drivers;

use Sofy\Broadcasting\BroadcastDriver;

class NullDriver implements BroadcastDriver
{
    public function broadcast(string $channel, string $event, array $data): void
    {
        // No-op
    }
}
