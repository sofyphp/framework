<?php

declare(strict_types=1);

namespace Sofy\Broadcasting\Drivers;

use Sofy\Broadcasting\BroadcastDriver;
use Sofy\Log\Log;

class LogDriver implements BroadcastDriver
{
    public function broadcast(string $channel, string $event, array $data): void
    {
        Log::debug("Broadcasting [$event] on channel [$channel]", $data);
    }
}
