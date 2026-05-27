<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class EmptyState extends Component
{
    public function __construct(
        private readonly string $title,
        private readonly string $description = '',
        private readonly mixed  $action      = null,
        private readonly string $icon        = '◯',
    ) {}

    public function render(): string
    {
        $desc = $this->description !== ''
            ? '<p class="sofy-empty-desc">' . $this->e($this->description) . '</p>'
            : '';
        $action = $this->action !== null
            ? '<div class="sofy-empty-action">' . $this->slot($this->action) . '</div>'
            : '';

        return '<div class="sofy-empty">'
            . '<div class="sofy-empty-icon">' . $this->e($this->icon) . '</div>'
            . '<div class="sofy-empty-title">' . $this->e($this->title) . '</div>'
            . $desc
            . $action
            . '</div>';
    }
}
