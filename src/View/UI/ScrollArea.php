<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class ScrollArea extends Component
{
    /**
     * @param mixed  $content    String or Component to wrap
     * @param string $height     CSS max-height (e.g. '300px', '50vh')
     * @param string $direction  vertical|horizontal|both
     */
    public function __construct(
        private readonly mixed  $content,
        private readonly string $height    = '320px',
        private readonly string $direction = 'vertical',
    ) {}

    public function render(): string
    {
        $style = match ($this->direction) {
            'horizontal' => 'max-width:100%;',
            default      => 'max-height:' . $this->e($this->height) . ';',
        };

        return '<div class="sofy-scroll sofy-scroll-' . $this->e($this->direction) . '" style="' . $style . '">'
            . $this->slot($this->content)
            . '</div>';
    }
}
