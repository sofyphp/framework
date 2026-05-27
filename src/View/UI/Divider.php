<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Divider extends Component
{
    public function render(): string
    {
        return '<hr class="sofy-hr">';
    }
}
