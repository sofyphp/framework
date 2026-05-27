<?php

declare(strict_types=1);

namespace Sofy\Notification;

use Sofy\Notification\Channels\DatabaseChannel;
use Sofy\Notification\Channels\MailChannel;
use Sofy\Notification\Channels\BroadcastChannel;

class Notifier
{
    /** @var array<string, class-string<NotificationChannel>> */
    private static array $customChannels = [];

    public static function extend(string $name, string $channelClass): void
    {
        self::$customChannels[$name] = $channelClass;
    }

    public static function send(mixed $notifiable, Notification $notification): void
    {
        self::dispatch($notifiable, $notification);
    }

    public static function sendNow(mixed $notifiable, Notification $notification): void
    {
        self::dispatch($notifiable, $notification);
    }

    private static function dispatch(mixed $notifiable, Notification $notification): void
    {
        $channels = $notification->via($notifiable);

        foreach ($channels as $channel) {
            self::resolveChannel($channel)->send($notifiable, $notification);
        }
    }

    private static function resolveChannel(string $name): NotificationChannel
    {
        if (isset(self::$customChannels[$name])) {
            return new self::$customChannels[$name]();
        }

        return match ($name) {
            'mail'      => new MailChannel(),
            'database'  => new DatabaseChannel(),
            'broadcast' => new BroadcastChannel(),
            default     => throw new \InvalidArgumentException("Unknown notification channel [$name]."),
        };
    }
}
