<?php

declare(strict_types=1);

namespace Sofy\View\UI;

/**
 * A chat thread: scrollable message bubbles (own vs others) + a composer.
 * Transport-agnostic — you give it a poll URL and a send URL:
 *
 *   - send  : POST {body} → persists a message, returns the created message JSON
 *   - poll  : GET ?after={id} → returns {messages: [{id,user_id,name,body,time,mine}]}
 *
 * Live updates default to polling (works everywhere, zero infra). Pass a
 * wsUrl + room and the component also opens a WebSocket: on send it emits a
 * tiny "bump" to the room, and on receiving a bump it fetches immediately — so
 * ws:serve gives instant delivery with no Redis bridge, and polling remains the
 * safety net. Markup-only; behaviour (sofyChat) + styles live in Page.
 *
 *   UI::chat($messages, sendUrl: '/admin/messages/5/send',
 *            pollUrl: '/admin/messages/5/poll', currentUserId: $me)
 *
 * @param list<array{id:int,user_id:int,name:string,body:string,time:string,mine?:bool}> $messages
 */
final class Chat extends Component
{
    private static int $seq = 0;

    public function __construct(
        private readonly array  $messages,
        private readonly string $sendUrl,
        private readonly string $pollUrl,
        private readonly int    $currentUserId,
        private readonly ?string $wsUrl = null,
        private readonly string $room = '',
        private readonly string $placeholder = 'Сообщение…',
        private readonly int    $pollInterval = 4000,
    ) {}

    public function render(): string
    {
        $id    = 'sofy-chat-' . (++self::$seq);
        $token = function_exists('csrf_token') ? csrf_token() : '';

        $bubbles = '';
        $lastId  = 0;
        foreach ($this->messages as $m) {
            $bubbles .= $this->bubble($m);
            $lastId   = max($lastId, (int) ($m['id'] ?? 0));
        }
        if ($bubbles === '') {
            $bubbles = '<div class="sofy-chat-empty">Сообщений пока нет — напишите первое.</div>';
        }

        $attrs = ' id="' . $id . '"'
            . ' data-send="' . $this->e($this->sendUrl) . '"'
            . ' data-poll="' . $this->e($this->pollUrl) . '"'
            . ' data-uid="' . $this->currentUserId . '"'
            . ' data-last="' . $lastId . '"'
            . ' data-interval="' . $this->pollInterval . '"'
            . ' data-token="' . $this->e($token) . '"';
        if ($this->wsUrl !== null && $this->wsUrl !== '') {
            $attrs .= ' data-ws="' . $this->e($this->wsUrl) . '" data-room="' . $this->e($this->room) . '"';
        }

        return '<div class="sofy-chat"' . $attrs . '>'
            . '<div class="sofy-chat-log">' . $bubbles . '</div>'
            . '<form class="sofy-chat-composer" onsubmit="return sofyChat.send(event,this)">'
            . '<textarea class="sofy-chat-input" name="body" rows="1" placeholder="' . $this->e($this->placeholder) . '"'
            . ' onkeydown="sofyChat.key(event,this)" required></textarea>'
            . '<button type="submit" class="sofy-btn sofy-btn-primary sofy-chat-send">Отправить</button>'
            . '</form>'
            . '</div>';
    }

    /** @param array{id?:int,user_id?:int,name?:string,body?:string,time?:string,mine?:bool} $m */
    private function bubble(array $m): string
    {
        $mine = $m['mine'] ?? ((int) ($m['user_id'] ?? 0) === $this->currentUserId);
        $cls  = 'sofy-chat-msg' . ($mine ? ' mine' : '');
        return '<div class="' . $cls . '" data-id="' . (int) ($m['id'] ?? 0) . '">'
            . ($mine ? '' : '<div class="sofy-chat-author">' . $this->e($m['name'] ?? '') . '</div>')
            . '<div class="sofy-chat-bubble">' . nl2br($this->e($m['body'] ?? '')) . '</div>'
            . '<div class="sofy-chat-time">' . $this->e($m['time'] ?? '') . '</div>'
            . '</div>';
    }
}
