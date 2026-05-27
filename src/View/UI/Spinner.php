<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Spinner extends Component
{
    /**
     * @param string $size    sm|md|lg
     * @param string $variant accent|muted|white
     */
    public function __construct(
        private readonly string $size    = 'md',
        private readonly string $variant = 'accent',
    ) {}

    public function render(): string
    {
        return '<span class="sofy-spinner sofy-spinner-' . $this->e($this->size)
            . ' sofy-spinner-' . $this->e($this->variant)
            . '" role="status" aria-label="Loading"></span>';
    }
}
