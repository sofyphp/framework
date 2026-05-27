<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Grid extends Component
{
    private array $items = [];

    public function __construct(private readonly int $cols = 2) {}

    public static function make(int $cols, array $items = []): static
    {
        $grid = new static($cols);
        foreach ($items as $item) {
            $grid->add($item);
        }
        return $grid;
    }

    public function add(mixed ...$items): static
    {
        array_push($this->items, ...$items);
        return $this;
    }

    public function render(): string
    {
        $cls   = 'sofy-grid sofy-g' . min(4, max(1, $this->cols));
        $cells = implode('', array_map(fn($item) => (string) $item, $this->items));

        return '<div class="' . $cls . '">' . $cells . '</div>';
    }
}
