<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Badge extends Component
{
    /** @param 'default'|'success'|'warning'|'danger'|'info'|'accent' $variant */
    public function __construct(
        private readonly string $label,
        private readonly string $variant = 'default',
    ) {}

    public function render(): string
    {
        return '<span class="sofy-badge sofy-badge-' . $this->e($this->variant) . '"' . $this->colorAttr() . '>'
            . $this->e($this->label)
            . '</span>';
    }
}
