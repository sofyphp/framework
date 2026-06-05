<?php

declare(strict_types=1);

namespace Sofy\Notification\Channels;

use Sofy\Database\Connection;
use Sofy\Notification\Notification;
use Sofy\Notification\NotificationChannel;

class DatabaseChannel implements NotificationChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        $data = $notification->toDatabase($notifiable);

        Connection::getDefault()->table('notifications')->insert([
            // No 'id' — the notifications table uses an auto-increment id().
            // Notification::$id is a hex string; inserting it into the
            // auto-increment integer id was a datatype mismatch on strict
            // databases (SQLite, Postgres). Present since the initial commit.
            'type'             => $notification::class,
            'notifiable_type'  => $notifiable::class,
            'notifiable_id'    => $notifiable->id ?? null,
            'data'             => json_encode($data, JSON_UNESCAPED_UNICODE),
            'read_at'          => null,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
    }
}
