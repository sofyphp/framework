<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class CopyButton extends Component
{
    public function __construct(
        private readonly string $text,
        private readonly string $label       = 'Copy',
        private readonly string $copiedLabel = 'Copied!',
        private readonly string $size        = 'sm',
    ) {}

    public function render(): string
    {
        return '<button type="button"'
            . ' class="sofy-copy-btn sofy-btn sofy-btn-ghost sofy-btn-' . $this->e($this->size) . '"'
            . ' data-copy="' . $this->e($this->text) . '"'
            . ' data-done="' . $this->e($this->copiedLabel) . '"'
            . ' onclick="sofyCopy(this)">'
            . '<span class="t-text-swap">' . $this->e($this->label) . '</span>'
            . '</button>';
    }
}