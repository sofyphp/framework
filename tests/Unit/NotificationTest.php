<?php

declare(strict_types=1);

namespace Tests\Unit;

use Main\Models\User;
use Messenger\Notifications\NewMessageNotification;
use Tests\TestCase;

/** Database notifications — the auto-increment-id fix in v0.9.0. */
final class NotificationTest extends TestCase
{
    protected function setUp(): void
    {
        $db = $this->freshDatabase();
        $db->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, password TEXT, created_at TEXT, updated_at TEXT)');
        $db->execute('CREATE TABLE notifications (id INTEGER PRIMARY KEY, type TEXT, notifiable_type TEXT, notifiable_id INTEGER, data TEXT, read_at TEXT, created_at TEXT, updated_at TEXT)');
        $db->execute("INSERT INTO users (id,name,email) VALUES (7,'Bob','b@x.co')");
    }

    public function test_notify_persists_with_integer_id_and_feed_shape(): void
    {
        $bob = User::find(7);
        $bob->notify(new NewMessageNotification('Alice', 'Hey there', 42));

        $rows = $this->db->query('SELECT id, data FROM notifications WHERE notifiable_id = ? AND read_at IS NULL', [7]);
        $this->assertCount(1, $rows);
        $this->assertIsInt($rows[0]['id']); // auto-increment integer, not a hex string

        $data = json_decode((string) $rows[0]['data'], true);
        $this->assertStringContainsString('Alice', $data['title']);
        $this->assertSame('Hey there', $data['body']);
        $this->assertSame('/admin/messages/42', $data['url']);
    }
}
