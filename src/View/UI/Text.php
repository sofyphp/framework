<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Text extends Component
{
    public function __construct(
        private readonly string $content,
        private readonly bool   $muted = false,
    ) {}

    public function render(): string
    {
        $cls = 'sofy-p' . ($this->muted ? ' sofy-muted' : '');
        return '<p class="' . $cls . '">' . $this->e($this->content) . '</p>';
    }
}
