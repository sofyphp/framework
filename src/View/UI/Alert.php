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

    /**
     * @param string|Component             $message Plain string is escaped; a Component (e.g. UI::raw('<b>…</b>'))
     *                                              is rendered as-is, mirroring how UI::card($content) treats its body.
     * @param 'success'|'warning'|'danger'|'info' $type
     * @param string|Component|null        $title   Same string-vs-Component treatment as $message.
     */
    public function __construct(
        private readonly string|Component      $message,
        private readonly string                $type    = 'info',
        private readonly string|Component|null $title   = null,
    ) {}

    public function render(): string
    {
        $icon = self::$icons[$this->type] ?? 'i';

        $body = $this->title !== null
            ? '<div class="sofy-alert-title">' . $this->renderPart($this->title) . '</div>' . $this->renderPart($this->message)
            : $this->renderPart($this->message);

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

    /** Strings get escaped; Components (incl. UI::raw) are trusted to emit safe HTML. */
    private function renderPart(string|Component $part): string
    {
        return $part instanceof Component ? (string) $part : $this->e($part);
    }
}
