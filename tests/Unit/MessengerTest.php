<?php

declare(strict_types=1);

namespace Tests\Unit;

use Messenger\Models\Channel;
use Messenger\Models\Message;
use Messenger\Models\Participant;
use Tests\TestCase;

final class MessengerTest extends TestCase
{
    protected function setUp(): void
    {
        $db = $this->freshDatabase();
        $db->execute('CREATE TABLE chat_channels (id INTEGER PRIMARY KEY, type TEXT, name TEXT, dm_key TEXT, created_by INTEGER, created_at TEXT, updated_at TEXT)');
        $db->execute('CREATE TABLE chat_participants (id INTEGER PRIMARY KEY, channel_id INTEGER, user_id INTEGER, last_read_at TEXT, created_at TEXT, updated_at TEXT)');
        $db->execute('CREATE TABLE chat_messages (id INTEGER PRIMARY KEY, channel_id INTEGER, user_id INTEGER, body TEXT, created_at TEXT, updated_at TEXT)');
    }

    public function test_direct_channel_is_deduped_by_pair(): void
    {
        $a = Channel::directBetween(3, 7);
        $b = Channel::directBetween(7, 3); // reverse order → same channel
        $this->assertSame((int) $a->id, (int) $b->id);
        $this->assertSame('3:7', $a->dm_key);
        $this->assertEqualsCanonicalizing([3, 7], $a->participantIds());
        $this->assertTrue($a->isParticipant(3));
        $this->assertFalse($a->isParticipant(9));
    }

    public function test_group_channel(): void
    {
        $g = Channel::createGroup('Team', 3, [7, 9]);
        $this->assertSame('group', $g->type);
        $this->assertEqualsCanonicalizing([3, 7, 9], $g->participantIds());
    }

    public function test_messages_since(): void
    {
        $c = Channel::directBetween(1, 2);
        Message::create(['channel_id' => $c->id, 'user_id' => 1, 'body' => 'hi']);
        Message::create(['channel_id' => $c->id, 'user_id' => 2, 'body' => 'yo']);
        $this->assertCount(2, Message::since((int) $c->id, 0));
        $this->assertCount(1, Message::since((int) $c->id, 1));
    }

    public function test_unread_excludes_own_and_clears_on_read(): void
    {
        $c = Channel::directBetween(1, 2);
        Message::create(['channel_id' => $c->id, 'user_id' => 2, 'body' => 'from other']);
        $this->assertSame(1, Participant::unreadCount(1)); // user 1 has 1 unread (from user 2)
        $this->assertSame(0, Participant::unreadCount(2)); // own message doesn't count
        Participant::markRead((int) $c->id, 1);
        $this->assertSame(0, Participant::unreadCount(1));
    }
}
