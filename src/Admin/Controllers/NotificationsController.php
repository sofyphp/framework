<?php

declare(strict_types=1);

namespace Sofy\Admin\Controllers;

use Sofy\Auth\Auth;
use Sofy\Database\Connection;
use Sofy\Http\Request;
use Sofy\Http\Response;

/**
 * Feeds the browser-notification poller (sofyNotify in Page). Any code that
 * calls `$user->notify(...)` with a database notification whose toDatabase()
 * returns a `title` (and optional `body`/`url`) surfaces here as a desktop
 * notification with sound, anywhere in the admin.
 *
 * Routes (admin-routes.php, behind EnsureAdmin):
 *   GET  /admin/notifications/feed?after=ID  → unread notifications as JSON
 *   POST /admin/notifications/seen           → mark {ids:[…]} (or all) read
 */
class NotificationsController
{
    public function feed(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return $this->json(['notifications' => []]);
        }

        try {
            $rows = Connection::getDefault()->query(
                'SELECT id, type, data, created_at FROM notifications
                  WHERE notifiable_id = ? AND notifiable_type = ? AND read_at IS NULL
                  ORDER BY id DESC LIMIT 20',
                [(int) $user->getAttribute('id'), $user::class],
            );
        } catch (\Throwable) {
            return $this->json(['notifications' => []]);
        }

        $out = [];
        foreach (array_reverse($rows) as $r) {
            $data = json_decode((string) $r['data'], true);
            $data = is_array($data) ? $data : [];
            $title = (string) ($data['title'] ?? $data['subject'] ?? 'Уведомление');
            $out[] = [
                'id'    => (int) $r['id'],
                'title' => $title,
                'body'  => (string) ($data['body'] ?? $data['message'] ?? ''),
                'url'   => (string) ($data['url'] ?? ''),
                'tag'   => (string) ($data['tag'] ?? ('n' . $r['id'])),
                'time'  => (string) ($r['created_at'] ?? ''),
            ];
        }
        return $this->json(['notifications' => $out]);
    }

    public function seen(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return $this->json(['ok' => false], 401);
        }

        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));
        try {
            $conn = Connection::getDefault();
            $uid  = (int) $user->getAttribute('id');
            $type = $user::class;
            if ($ids === []) {
                $conn->execute(
                    'UPDATE notifications SET read_at = ? WHERE notifiable_id = ? AND notifiable_type = ? AND read_at IS NULL',
                    [date('Y-m-d H:i:s'), $uid, $type],
                );
            } else {
                $place = implode(',', array_fill(0, count($ids), '?'));
                $conn->execute(
                    "UPDATE notifications SET read_at = ? WHERE notifiable_id = ? AND notifiable_type = ? AND id IN ($place)",
                    array_merge([date('Y-m-d H:i:s'), $uid, $type], $ids),
                );
            }
        } catch (\Throwable) {
            return $this->json(['ok' => false], 500);
        }
        return $this->json(['ok' => true]);
    }

    private function json(array $data, int $status = 200): Response
    {
        return new Response(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }
}
