<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Banner extends Component
{
    private static array $icons = [
        'success' => '✓',
        'warning' => '⚠',
        'danger'  => '✕',
        'info'    => 'ℹ',
    ];

    public function __construct(
        private readonly string $message,
        private readonly string $variant     = 'info',
        private readonly mixed  $action      = null,
        private readonly bool   $dismissible = false,
    ) {}

    public function render(): string
    {
        $icon   = self::$icons[$this->variant] ?? 'ℹ';
        $action = $this->action !== null
            ? '<div class="sofy-banner-action">' . $this->slot($this->action) . '</div>'
            : '';
        $close  = $this->dismissible
            ? '<button type="button" class="sofy-banner-close" aria-label="Dismiss"'
              . ' onclick="this.closest(\'.sofy-banner\').remove()">×</button>'
            : '';

        return '<div class="sofy-banner sofy-banner-' . $this->e($this->variant) . '">'
            . '<span class="sofy-banner-icon">' . $icon . '</span>'
            . '<span class="sofy-banner-msg">' . $this->e($this->message) . '</span>'
            . $action
            . $close
            . '</div>';
    }
}