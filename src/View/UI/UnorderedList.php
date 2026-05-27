<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class UnorderedList extends Component
{
    public function __construct(private readonly array $items) {}

    public function render(): string
    {
        $items = implode('', array_map(
            fn($item) => '<li>' . $this->e((string) $item) . '</li>',
            $this->items
        ));
        return '<ul class="sofy-ul">' . $items . '</ul>';
    }
}
