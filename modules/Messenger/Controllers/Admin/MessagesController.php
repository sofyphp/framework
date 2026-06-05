<?php

declare(strict_types=1);

namespace Messenger\Controllers\Admin;

use Main\Models\User;
use Messenger\Models\Channel;
use Messenger\Models\Message;
use Messenger\Models\Participant;
use Sofy\Admin\Admin;
use Sofy\Auth\Auth;
use Sofy\Database\Connection;
use Sofy\Http\Request;
use Sofy\Http\Response;
use Sofy\View\UI;
use Sofy\View\UI\Card;

/**
 * Admin messaging: a list of conversations + a live chat thread. 1:1 DMs and
 * named group channels. Live updates via polling, upgradeable to WebSocket
 * push (see config('messenger.ws_url')).
 */
class MessagesController
{
    public function index(Request $request): Response
    {
        $me = Auth::id();
        if ($me === null) return Response::redirect('/admin/login');

        if ($this->migrationsMissing()) return $this->needsMigration();

        $sidebar = UI::grid(1, [
            UI::card('Диалоги', UI::chatList($this->channelListItems($me, 0))),
            $this->startCard($this->otherUsers($me)),
        ]);
        $main = UI::card(null, UI::emptyState(
            'Выберите диалог',
            'Откройте диалог слева или начните новый.',
            icon: '💬',
        ));

        return Admin::page('Сообщения')
            ->header('Сообщения')
            ->add(UI::sidebarLayout($sidebar, $main, width: '300px'))
            ->response();
    }

    public function show(Request $request, int|string $id): Response
    {
        $me = Auth::id();
        if ($me === null) return Response::redirect('/admin/login');
        if ($this->migrationsMissing()) return $this->needsMigration();

        $channel = Channel::find((int) $id);
        if ($channel === null || !$channel->isParticipant($me)) {
            return Admin::page('Диалог не найден')
                ->header('Диалог не найден')
                ->add(UI::alert('Диалог не существует или вы не его участник.', 'warning'))
                ->response();
        }

        Participant::markRead((int) $channel->id, $me);

        $messages = $this->formatMessages(Message::since((int) $channel->id, 0), $me);
        $title    = $this->channelTitle($channel, $me);

        $wsUrl = (string) config('messenger.ws_url', '');
        $chat  = UI::chat(
            messages: $messages,
            sendUrl: '/admin/messages/' . (int) $channel->id . '/send',
            pollUrl: '/admin/messages/' . (int) $channel->id . '/poll',
            currentUserId: $me,
            wsUrl: $wsUrl !== '' ? $wsUrl : null,
            room: 'chat.' . (int) $channel->id,
        );

        $sidebar = UI::card('Диалоги', UI::chatList($this->channelListItems($me, (int) $channel->id)));

        return Admin::page($title)
            ->header($title, UI::button('← Все диалоги', '/admin/messages', 'ghost'))
            ->add(UI::sidebarLayout($sidebar, $chat, width: '300px'))
            ->response();
    }

    public function send(Request $request, int|string $id): Response
    {
        $me = Auth::id();
        if ($me === null) return $this->json(['error' => 'unauthenticated'], 401);

        $channel = Channel::find((int) $id);
        if ($channel === null || !$channel->isParticipant($me)) {
            return $this->json(['error' => 'forbidden'], 403);
        }

        $body = trim((string) $request->input('body', ''));
        if ($body === '') return $this->json(['error' => 'empty'], 422);
        if (mb_strlen($body) > 5000) $body = mb_substr($body, 0, 5000);

        $msg = Message::create([
            'channel_id' => (int) $channel->id,
            'user_id'    => $me,
            'body'       => $body,
        ]);
        Participant::markRead((int) $channel->id, $me);

        $this->notifyOthers($channel, $me, $body);

        $formatted = $this->formatMessages([$msg], $me);
        return $this->json(['message' => $formatted[0] ?? null]);
    }

    public function poll(Request $request, int|string $id): Response
    {
        $me = Auth::id();
        if ($me === null) return $this->json(['messages' => []]);

        $channel = Channel::find((int) $id);
        if ($channel === null || !$channel->isParticipant($me)) {
            return $this->json(['messages' => []]);
        }

        $after = (int) $request->input('after', 0);
        $new   = Message::since((int) $channel->id, $after);
        if ($new !== []) {
            Participant::markRead((int) $channel->id, $me);
        }
        return $this->json(['messages' => $this->formatMessages($new, $me)]);
    }

    public function startDirect(Request $request): Response
    {
        $me = Auth::id();
        if ($me === null) return Response::redirect('/admin/login');

        $userId = (int) $request->input('user_id', 0);
        if ($userId <= 0 || $userId === $me) return Response::redirect('/admin/messages');

        $channel = Channel::directBetween($me, $userId);
        return Response::redirect('/admin/messages/' . (int) $channel->id);
    }

    public function startGroup(Request $request): Response
    {
        $me = Auth::id();
        if ($me === null) return Response::redirect('/admin/login');

        $name = trim((string) $request->input('name', ''));
        $ids  = array_map('intval', (array) $request->input('user_ids', []));
        $ids  = array_values(array_filter($ids, static fn($x) => $x > 0));
        if ($name === '' || $ids === []) return Response::redirect('/admin/messages');

        $channel = Channel::createGroup($name, $me, $ids);
        return Response::redirect('/admin/messages/' . (int) $channel->id);
    }

    /**
     * Drop a database notification to every other participant so the core
     * browser-notification poller alerts them (desktop + sound) anywhere in
     * the admin. Best-effort — a missing notifications table never blocks the
     * message itself.
     */
    private function notifyOthers(Channel $channel, int $me, string $body): void
    {
        try {
            $sender  = User::find($me);
            $name    = $sender !== null ? (string) $sender->getAttribute('name') : ('User #' . $me);
            $preview = mb_strlen($body) > 80 ? mb_substr($body, 0, 80) . '…' : $body;
            $notice  = new \Messenger\Notifications\NewMessageNotification($name, $preview, (int) $channel->id);

            foreach ($channel->participantIds() as $uid) {
                if ($uid === $me) continue;
                $u = User::find($uid);
                if ($u !== null && method_exists($u, 'notify')) {
                    $u->notify($notice);
                }
            }
        } catch (\Throwable) {
            // notifications optional — never break sending a message
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return list<array{id:int,user_id:int,name:string,body:string,time:string,mine:bool}> */
    private function formatMessages(array $messages, int $me): array
    {
        if ($messages === []) return [];

        // Resolve sender names in one query.
        $ids = array_values(array_unique(array_map(static fn($m) => (int) $m->user_id, $messages)));
        $names = [];
        try {
            foreach (User::query()->whereIn('id', $ids)->get() as $u) {
                $names[(int) $u->getAttribute('id')] = (string) $u->getAttribute('name');
            }
        } catch (\Throwable) {
            // fall back to "User #id"
        }

        $out = [];
        foreach ($messages as $m) {
            $uid = (int) $m->user_id;
            $out[] = [
                'id'      => (int) $m->id,
                'user_id' => $uid,
                'name'    => $names[$uid] ?? ('User #' . $uid),
                'body'    => (string) $m->body,
                'time'    => $this->time((string) ($m->created_at ?? '')),
                'mine'    => $uid === $me,
            ];
        }
        return $out;
    }

    private function time(string $ts): string
    {
        if ($ts === '') return '';
        $t = strtotime($ts);
        return $t ? date('d.m H:i', $t) : $ts;
    }

    /**
     * Conversation rows shaped for UI::chatList.
     *
     * @return list<array{title:string,preview:string,unread:int,href:string,active:bool}>
     */
    private function channelListItems(int $me, int $activeId): array
    {
        $rows = Connection::getDefault()->query(
            'SELECT c.id, c.type, c.name
               FROM chat_channels c
               JOIN chat_participants p ON p.channel_id = c.id AND p.user_id = ?
              ORDER BY c.id DESC',
            [$me],
        );
        $out = [];
        foreach ($rows as $r) {
            $cid = (int) $r['id'];
            $channel = new Channel((array) $r);
            $channel->id = $cid;
            $out[] = [
                'title'   => $this->channelTitle($channel, $me),
                'preview' => $this->lastPreview($cid),
                'unread'  => $this->channelUnread($cid, $me),
                'href'    => '/admin/messages/' . $cid,
                'active'  => $cid === $activeId,
            ];
        }
        return $out;
    }

    private function channelTitle(Channel $channel, int $me): string
    {
        if ((string) $channel->type === 'group') {
            return (string) ($channel->name ?: 'Группа #' . $channel->id);
        }
        // DM: the other participant's name.
        foreach ($channel->participantIds() as $uid) {
            if ($uid !== $me) {
                try {
                    $u = User::find($uid);
                    return $u !== null ? (string) $u->getAttribute('name') : ('User #' . $uid);
                } catch (\Throwable) {
                    return 'User #' . $uid;
                }
            }
        }
        return 'Диалог';
    }

    private function lastPreview(int $channelId): string
    {
        $rows = Connection::getDefault()->query(
            'SELECT body FROM chat_messages WHERE channel_id = ? ORDER BY id DESC LIMIT 1',
            [$channelId],
        );
        $body = (string) ($rows[0]['body'] ?? '');
        return mb_strlen($body) > 48 ? mb_substr($body, 0, 48) . '…' : $body;
    }

    private function channelUnread(int $channelId, int $me): int
    {
        $rows = Connection::getDefault()->query(
            'SELECT COUNT(*) AS c
               FROM chat_messages m
               JOIN chat_participants p ON p.channel_id = m.channel_id AND p.user_id = ?
              WHERE m.channel_id = ? AND m.user_id <> ?
                AND (p.last_read_at IS NULL OR m.created_at > p.last_read_at)',
            [$me, $channelId, $me],
        );
        return (int) ($rows[0]['c'] ?? 0);
    }

    /** @return list<array{id:int,name:string}> */
    private function otherUsers(int $me): array
    {
        $out = [];
        try {
            foreach (User::query()->where('id', '<>', $me)->orderBy('name')->limit(500)->get() as $u) {
                $out[] = ['id' => (int) $u->getAttribute('id'), 'name' => (string) $u->getAttribute('name')];
            }
        } catch (\Throwable) {
        }
        return $out;
    }

    private function startCard(array $users): Card
    {
        $opts = [];
        foreach ($users as $u) $opts[(string) $u['id']] = $u['name'];

        $dm = UI::form('/admin/messages/start-direct', 'POST')
            ->combobox('Написать пользователю', 'user_id', $opts, placeholder: 'Найти пользователя…', required: true)
            ->submit('Начать диалог', 'primary');

        return UI::card('Новый диалог', $dm);
    }

    private function json(array $data, int $status = 200): Response
    {
        return new Response(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    private function migrationsMissing(): bool
    {
        try {
            Connection::getDefault()->query('SELECT 1 FROM chat_channels LIMIT 1');
            return false;
        } catch (\Throwable) {
            return true;
        }
    }

    private function needsMigration(): Response
    {
        return Admin::page('Сообщения')
            ->header('Сообщения')
            ->add(UI::alert(
                UI::raw('Таблицы чата ещё не созданы. Запустите <code class="sofy-docs-code">php sofy migrate</code>.'),
                'warning',
                'Нет таблиц мессенджера',
            ))
            ->response();
    }
}
