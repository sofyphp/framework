<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Code extends Component
{
    public function __construct(
        private readonly string $code,
        private readonly string $language = '',
    ) {}

    public function render(): string
    {
        $lang = $this->language !== ''
            ? '<div class="sofy-code-lang">' . $this->e($this->language) . '</div>'
            : '';

        return '<div class="sofy-code-wrap">'
            . $lang
            . '<pre class="sofy-code">' . $this->e($this->code) . '</pre>'
            . '</div>';
    }
}
