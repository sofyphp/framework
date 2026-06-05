<?php

declare(strict_types=1);

namespace Sofy\View\UI;

/**
 * A list of conversations — companion to UI::chat. Each row is a link with a
 * title, a last-message preview and an unread badge; the active one is
 * highlighted. Styles ship from Page.
 *
 *   UI::chatList([
 *       ['title' => 'Алиса', 'preview' => 'Привет!', 'unread' => 2,
 *        'href' => '/admin/messages/5', 'active' => true],
 *   ]);
 *
 * @param list<array{title:string,preview?:string,unread?:int,href:string,active?:bool}> $items
 */
final class ChatList extends Component
{
    public function __construct(
        private readonly array $items,
        private readonly string $empty = 'Диалогов пока нет.',
    ) {}

    public function render(): string
    {
        if ($items = $this->items) {
            $html = '';
            foreach ($items as $it) {
                $active = ($it['active'] ?? false) ? ' active' : '';
                $unread = (int) ($it['unread'] ?? 0);
                $badge  = $unread > 0 ? '<span class="sofy-chatlist-badge">' . $unread . '</span>' : '';
                $prev   = (string) ($it['preview'] ?? '');
                $html  .= '<a class="sofy-chatlist-item' . $active . '" href="' . $this->e($it['href'] ?? '#') . '">'
                    . '<div class="sofy-chatlist-top">'
                    . '<span class="sofy-chatlist-name">' . $this->e($it['title'] ?? '') . '</span>' . $badge
                    . '</div>'
                    . ($prev !== '' ? '<div class="sofy-chatlist-preview">' . $this->e($prev) . '</div>' : '')
                    . '</a>';
            }
            return '<div class="sofy-chatlist">' . $html . '</div>';
        }

        return '<div class="sofy-chatlist-empty">' . $this->e($this->empty) . '</div>';
    }
}
