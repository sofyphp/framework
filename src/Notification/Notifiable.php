<?php

declare(strict_types=1);

namespace Sofy\Notification;

use Sofy\Database\Connection;

/**
 * Add this trait to any model that should receive notifications.
 *
 *   class User extends Model
 *   {
 *       use Notifiable;
 *   }
 *
 *   $user->notify(new InvoicePaid($invoice));
 *   $user->notifications();           // all stored notifications
 *   $user->unreadNotifications();     // unread only
 *   $user->markNotificationsRead();   // mark all read
 */
trait Notifiable
{
    public function notify(Notification $notification): void
    {
        Notifier::send($this, $notification);
    }

    public function notifyNow(Notification $notification): void
    {
        Notifier::sendNow($this, $notification);
    }

    /** Override to customise the email address for mail notifications. */
    public function routeNotificationForMail(): ?string
    {
        return $this->email ?? null;
    }

    // ── Database notifications (requires notifications table) ─────────────────

    public function notifications(): array
    {
        return Connection::getDefault()->query(
            'SELECT * FROM notifications WHERE notifiable_id = ? AND notifiable_type = ? ORDER BY created_at DESC',
            [$this->id ?? null, static::class]
        );
    }

    public function unreadNotifications(): array
    {
        return Connection::getDefault()->query(
            'SELECT * FROM notifications WHERE notifiable_id = ? AND notifiable_type = ? AND read_at IS NULL ORDER BY created_at DESC',
            [$this->id ?? null, static::class]
        );
    }

    public function markNotificationsRead(): void
    {
        Connection::getDefault()->execute(
            'UPDATE notifications SET read_at = ? WHERE notifiable_id = ? AND notifiable_type = ? AND read_at IS NULL',
            [date('Y-m-d H:i:s'), $this->id ?? null, static::class]
        );
    }
}
