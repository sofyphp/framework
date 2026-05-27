<?php

declare(strict_types=1);

namespace Sofy\Broadcasting;

interface BroadcastDriver
{
    public function broadcast(string $channel, string $event, array $data): void;
}
