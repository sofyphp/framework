<?php

declare(strict_types=1);

namespace Sofy\View\UI;

/**
 * CSS-only tooltip wrapper — no JavaScript required.
 *
 * Usage:
 *   UI::tooltip(UI::button('Save', '/save', 'primary'), 'Ctrl+S')
 *   UI::tooltip(UI::badge('active', 'success'), 'Last active 2 min ago', 'bottom')
 *
 * @param string $placement top|bottom|left|right
 */
class Tooltip extends Component
{
    public function __construct(
        private readonly mixed  $content,
        private readonly string $text,
        private readonly string $placement = 'top',
    ) {}

    public function render(): string
    {
        return '<span class="sofy-tip sofy-tip-' . $this->e($this->placement) . '" data-tip="' . $this->e($this->text) . '">'
            . $this->slot($this->content)
            . '</span>';
    }
}
