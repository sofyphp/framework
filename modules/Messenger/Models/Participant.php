<?php

declare(strict_types=1);

namespace Messenger\Models;

use Sofy\Database\Connection;
use Sofy\Database\Model;

/**
 * Membership of a user in a channel, plus their read cursor (last_read_at)
 * for unread counts.
 *
 * @property int         $id
 * @property int         $channel_id
 * @property int         $user_id
 * @property string|null $last_read_at
 */
class Participant extends Model
{
    protected static string $table = 'chat_participants';

    protected static array $fillable = ['channel_id', 'user_id', 'last_read_at'];

    protected static array $casts = [
        'channel_id' => 'int',
        'user_id'    => 'int',
    ];

    /** Add a user to a channel if not already a member. */
    public static function ensure(int $channelId, int $userId): void
    {
        $exists = static::query()
            ->where('channel_id', $channelId)
            ->where('user_id', $userId)
            ->first();
        if ($exists === null) {
            static::create(['channel_id' => $channelId, 'user_id' => $userId, 'last_read_at' => null]);
        }
    }

    /** Mark every message in a channel as read for a user (now). */
    public static function markRead(int $channelId, int $userId): void
    {
        Connection::getDefault()->execute(
            'UPDATE chat_participants SET last_read_at = ? WHERE channel_id = ? AND user_id = ?',
            [date('Y-m-d H:i:s'), $channelId, $userId],
        );
    }

    /** Total unread messages across all of a user's channels (for the badge). */
    public static function unreadCount(int $userId): int
    {
        $rows = Connection::getDefault()->query(
            'SELECT COUNT(*) AS c
               FROM chat_messages m
               JOIN chat_participants p
                 ON p.channel_id = m.channel_id AND p.user_id = ?
              WHERE m.user_id <> ?
                AND (p.last_read_at IS NULL OR m.created_at > p.last_read_at)',
            [$userId, $userId],
        );
        return (int) ($rows[0]['c'] ?? 0);
    }
}
