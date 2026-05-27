<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Accordion extends Component
{
    /**
     * @param array<array{title: string, content: string|Component, open?: bool}> $items
     */
    public function __construct(private readonly array $items) {}

    public function render(): string
    {
        $html = '<div class="sofy-accordion">';
        foreach ($this->items as $item) {
            $open    = !empty($item['open']) ? ' open' : '';
            $content = $item['content'] instanceof Component
                ? (string) $item['content']
                : $this->e((string) $item['content']);
            $html .= '<details' . $open . '>'
                . '<summary>' . $this->e($item['title']) . '</summary>'
                . '<div class="sofy-accordion-body">' . $content . '</div>'
                . '</details>';
        }
        return $html . '</div>';
    }
}
