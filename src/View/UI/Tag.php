<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Tag extends Component
{
    public function __construct(
        private readonly string  $label,
        private readonly string  $variant    = 'default',
        private readonly ?string $href        = null,
        private readonly bool    $removable   = false,
        private readonly ?string $removeUrl   = null,
    ) {}

    public function render(): string
    {
        $cls    = 'sofy-tag sofy-tag-' . $this->e($this->variant);
        $remove = '';

        if ($this->removable) {
            if ($this->removeUrl !== null) {
                $remove = '<a href="' . $this->e($this->removeUrl) . '" class="sofy-tag-rm" aria-label="Remove">×</a>';
            } else {
                $remove = '<button type="button" class="sofy-tag-rm" aria-label="Remove"'
                    . ' onclick="this.closest(\'.sofy-tag\').remove()">×</button>';
            }
        }

        $inner = $this->e($this->label) . $remove;

        if ($this->href !== null) {
            return '<a href="' . $this->e($this->href) . '" class="' . $cls . '"' . $this->colorAttr() . '>' . $inner . '</a>';
        }

        return '<span class="' . $cls . '"' . $this->colorAttr() . '>' . $inner . '</span>';
    }
}