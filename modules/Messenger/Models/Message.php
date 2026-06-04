<?php

declare(strict_types=1);

namespace Messenger\Models;

use Sofy\Database\Model;

/**
 * A single chat message in a channel.
 *
 * @property int    $id
 * @property int    $channel_id
 * @property int    $user_id
 * @property string $body
 * @property string $created_at
 */
class Message extends Model
{
    protected static string $table = 'chat_messages';

    protected static array $fillable = ['channel_id', 'user_id', 'body'];

    protected static array $casts = [
        'channel_id' => 'int',
        'user_id'    => 'int',
    ];

    /**
     * Messages in a channel after a given id (for polling), oldest first.
     *
     * @return list<Message>
     */
    public static function since(int $channelId, int $afterId = 0, int $limit = 200): array
    {
        return static::query()
            ->where('channel_id', $channelId)
            ->where('id', '>', $afterId)
            ->orderBy('id', 'ASC')
            ->limit($limit)
            ->get();
    }
}
