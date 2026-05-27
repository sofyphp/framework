<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class RawHtml extends Component
{
    public function __construct(private readonly string $html) {}

    public function render(): string
    {
        return $this->html;
    }
}
