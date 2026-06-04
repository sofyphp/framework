<?php

declare(strict_types=1);

namespace Messenger\Models;

use Sofy\Database\Connection;
use Sofy\Database\Model;

/**
 * A conversation: a 1:1 direct message (type=direct, two participants, a
 * canonical dm_key) or a named group (type=group, N participants).
 *
 * @property int         $id
 * @property string      $type
 * @property string|null $name
 * @property string|null $dm_key
 * @property int|null    $created_by
 */
class Channel extends Model
{
    protected static string $table = 'chat_channels';

    protected static array $fillable = ['type', 'name', 'dm_key', 'created_by'];

    protected static array $casts = [
        'created_by' => 'int',
    ];

    /** Canonical key for a DM between two users — order-independent. */
    public static function dmKey(int $a, int $b): string
    {
        return min($a, $b) . ':' . max($a, $b);
    }

    /**
     * Find or create the direct channel between two users, ensuring both are
     * participants. Returns the Channel.
     */
    public static function directBetween(int $userA, int $userB): self
    {
        $key = self::dmKey($userA, $userB);

        /** @var self|null $channel */
        $channel = static::query()->where('dm_key', $key)->first();
        if ($channel === null) {
            $channel = static::create([
                'type'       => 'direct',
                'name'       => null,
                'dm_key'     => $key,
                'created_by' => $userA,
            ]);
            Participant::ensure((int) $channel->id, $userA);
            Participant::ensure((int) $channel->id, $userB);
        }
        return $channel;
    }

    /** Create a named group with the given participant user ids. */
    public static function createGroup(string $name, int $creator, array $userIds): self
    {
        $channel = static::create([
            'type'       => 'group',
            'name'       => $name,
            'dm_key'     => null,
            'created_by' => $creator,
        ]);
        $ids = array_unique(array_map('intval', array_merge([$creator], $userIds)));
        foreach ($ids as $uid) {
            Participant::ensure((int) $channel->id, $uid);
        }
        return $channel;
    }

    /** @return list<int> participant user ids */
    public function participantIds(): array
    {
        $rows = Connection::getDefault()->query(
            'SELECT user_id FROM chat_participants WHERE channel_id = ?',
            [(int) $this->id],
        );
        return array_map(static fn($r) => (int) $r['user_id'], $rows);
    }

    public function isParticipant(int $userId): bool
    {
        return in_array($userId, $this->participantIds(), true);
    }
}
