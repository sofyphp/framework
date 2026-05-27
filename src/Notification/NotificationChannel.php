<?php

declare(strict_types=1);

namespace Sofy\Notification;

interface NotificationChannel
{
    public function send(mixed $notifiable, Notification $notification): void;
}
