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

        $channels = $this->channelsFor($me);
        $users    = $this->otherUsers($me);

        return Admin::page('Сообщения')
            ->header('Сообщения')
            ->add(
                UI::raw('<div class="sofy-msg-layout">'),
                UI::raw('<div class="sofy-msg-list">'),
                $this->channelListCard($channels, 0),
                $this->startCard($users),
                UI::raw('</div><div class="sofy-msg-main">'),
                UI::card(null, UI::raw('<div class="sofy-msg-placeholder">Выберите диалог слева или начните новый.</div>')),
                UI::raw('</div></div>'),
                UI::raw($this->styles()),
            )
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

        return Admin::page($title)
            ->header($title, UI::button('← Все диалоги', '/admin/messages', 'ghost'))
            ->add(
                UI::raw('<div class="sofy-msg-layout">'),
                UI::raw('<div class="sofy-msg-list">'),
                $this->channelListCard($this->channelsFor($me), (int) $channel->id),
                UI::raw('</div><div class="sofy-msg-main">'),
                $chat,
                UI::raw('</div>'),
                UI::raw($this->styles()),
            )
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

    /** @return list<array{id:int,title:string,preview:string,unread:int,active:bool}> */
    private function channelsFor(int $me): array
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
                'id'      => $cid,
                'title'   => $this->channelTitle($channel, $me),
                'preview' => $this->lastPreview($cid),
                'unread'  => $this->channelUnread($cid, $me),
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

    private function channelListCard(array $channels, int $activeId): Card
    {
        $html = '';
        foreach ($channels as $c) {
            $active = $c['id'] === $activeId ? ' active' : '';
            $badge  = $c['unread'] > 0 ? '<span class="sofy-msg-badge">' . (int) $c['unread'] . '</span>' : '';
            $html  .= '<a class="sofy-msg-item' . $active . '" href="/admin/messages/' . (int) $c['id'] . '">'
                . '<div class="sofy-msg-item-top"><span class="sofy-msg-name">' . htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8') . '</span>' . $badge . '</div>'
                . '<div class="sofy-msg-preview">' . htmlspecialchars($c['preview'], ENT_QUOTES, 'UTF-8') . '</div>'
                . '</a>';
        }
        if ($html === '') $html = '<div class="sofy-msg-empty">Диалогов пока нет.</div>';
        return UI::card('Диалоги', UI::raw($html));
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

    private function styles(): string
    {
        return <<<CSS
        <style>
            .sofy-msg-layout{display:grid;grid-template-columns:300px 1fr;gap:18px;align-items:start}
            .sofy-msg-item{display:block;padding:10px 12px;border-radius:10px;text-decoration:none;color:var(--text);margin-bottom:4px}
            .sofy-msg-item:hover{background:rgba(0,0,0,.03)}
            .sofy-msg-item.active{background:rgba(217,119,87,.12)}
            .sofy-msg-item-top{display:flex;justify-content:space-between;align-items:center;gap:8px}
            .sofy-msg-name{font-weight:600;font-size:13px}
            .sofy-msg-preview{font-size:12px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .sofy-msg-badge{background:var(--accent);color:#fff;font-size:11px;font-weight:700;border-radius:10px;padding:1px 7px;flex-shrink:0}
            .sofy-msg-empty,.sofy-msg-placeholder{color:var(--muted);font-size:13px;padding:8px}
            @media(max-width:760px){.sofy-msg-layout{grid-template-columns:1fr}}
        </style>
        CSS;
    }
}
