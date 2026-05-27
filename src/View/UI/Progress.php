<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Progress extends Component
{
    /**
     * @param int     $value    0–100
     * @param string  $variant  accent|success|warning|danger|info
     * @param string  $size     sm|md|lg
     */
    public function __construct(
        private readonly int     $value,
        private readonly ?string $label   = null,
        private readonly string  $variant = 'accent',
        private readonly string  $size    = 'md',
        private readonly bool    $showPct = true,
    ) {}

    public function render(): string
    {
        $pct = max(0, min(100, $this->value));

        $header = '';
        if ($this->label !== null || $this->showPct) {
            $label  = $this->label !== null ? '<span>' . $this->e($this->label) . '</span>' : '<span></span>';
            $pctStr = $this->showPct ? '<span>' . $pct . '%</span>' : '';
            $header = '<div class="sofy-progress-hdr">' . $label . $pctStr . '</div>';
        }

        return '<div class="sofy-progress">'
            . $header
            . '<div class="sofy-progress-track ' . $this->e($this->size) . '">'
            . '<div class="sofy-progress-fill ' . $this->e($this->variant) . '" style="width:' . $pct . '%"></div>'
            . '</div>'
            . '</div>';
    }
}
