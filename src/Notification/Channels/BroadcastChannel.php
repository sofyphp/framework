<?php

declare(strict_types=1);

namespace Sofy\Notification\Channels;

use Sofy\Broadcasting\Broadcaster;
use Sofy\Notification\Notification;
use Sofy\Notification\NotificationChannel;

class BroadcastChannel implements NotificationChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        $data = $notification->toBroadcast($notifiable);
        if (empty($data)) {
            return;
        }

        $channel = $data['channel'] ?? ('App.User.' . ($notifiable->id ?? 0));
        $event   = $data['event']   ?? 'Notification';
        $payload = $data['data']    ?? [];

        Broadcaster::broadcast($channel, $event, $payload);
    }
}
