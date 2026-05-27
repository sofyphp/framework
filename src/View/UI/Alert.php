<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Alert extends Component
{
    private static array $icons = [
        'success' => '✓',
        'warning' => '!',
        'danger'  => '✕',
        'info'    => 'i',
    ];

    /** @param 'success'|'warning'|'danger'|'info' $type */
    public function __construct(
        private readonly string  $message,
        private readonly string  $type    = 'info',
        private readonly ?string $title   = null,
    ) {}

    public function render(): string
    {
        $icon = self::$icons[$this->type] ?? 'i';

        $body = $this->title
            ? '<div class="sofy-alert-title">' . $this->e($this->title) . '</div>' . $this->e($this->message)
            : $this->e($this->message);

        return sprintf(
            '<div class="sofy-alert sofy-alert-%s">'
            . '<span class="sofy-alert-icon">%s</span>'
            . '<div class="sofy-alert-body">%s</div>'
            . '</div>',
            $this->e($this->type),
            $icon,
            $body
        );
    }
}
