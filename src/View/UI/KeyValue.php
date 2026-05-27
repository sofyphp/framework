<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class KeyValue extends Component
{
    /**
     * @param array<string, mixed> $items   ['Label' => 'Value', ...] — value may be a Component
     * @param 'inline'|'stacked'   $layout
     */
    public function __construct(
        private readonly array  $items,
        private readonly string $layout = 'inline',
    ) {}

    public function render(): string
    {
        $rows = '';
        foreach ($this->items as $key => $val) {
            $value = $val instanceof Component
                ? (string) $val
                : $this->e((string) $val);
            $rows .= '<div class="sofy-kv-row">'
                . '<span class="sofy-kv-key">' . $this->e((string) $key) . '</span>'
                . '<span class="sofy-kv-val">' . $value . '</span>'
                . '</div>';
        }
        return '<dl class="sofy-kv ' . $this->e($this->layout) . '">' . $rows . '</dl>';
    }
}
