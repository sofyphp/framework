<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Timeline extends Component
{
    /**
     * @param array<array{
     *     title: string,
     *     time?: string,
     *     content?: string|Component,
     *     variant?: string,
     * }> $items
     */
    public function __construct(private readonly array $items) {}

    public function render(): string
    {
        $html = '<div class="sofy-timeline">';
        foreach ($this->items as $item) {
            $variant = $item['variant'] ?? 'accent';
            $time    = isset($item['time']) && $item['time'] !== ''
                ? '<span class="sofy-tl-time">' . $this->e($item['time']) . '</span>'
                : '';
            $body    = '';
            if (!empty($item['content'])) {
                $body = $item['content'] instanceof Component
                    ? (string) $item['content']
                    : $this->e((string) $item['content']);
                $body = '<div class="sofy-tl-content">' . $body . '</div>';
            }
            $html .= '<div class="sofy-tl-item">'
                . '<div class="sofy-tl-dot sofy-tl-dot-' . $this->e($variant) . '"></div>'
                . '<div class="sofy-tl-body">'
                . '<div class="sofy-tl-hdr">'
                . '<span class="sofy-tl-title">' . $this->e($item['title']) . '</span>'
                . $time
                . '</div>'
                . $body
                . '</div>'
                . '</div>';
        }
        return $html . '</div>';
    }
}
