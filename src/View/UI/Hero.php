<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Hero extends Component
{
    /** @var array<Button> */
    private array $actions = [];

    public function __construct(
        private readonly string $title,
        private readonly string $subtitle = '',
    ) {}

    public function action(Button ...$buttons): static
    {
        array_push($this->actions, ...$buttons);
        return $this;
    }

    public function render(): string
    {
        $sub = $this->subtitle !== ''
            ? '<p class="sofy-hero-sub">' . $this->e($this->subtitle) . '</p>'
            : '';

        $actions = !empty($this->actions)
            ? '<div class="sofy-hero-actions">' . implode('', array_map(fn($b) => (string) $b, $this->actions)) . '</div>'
            : '';

        return '<div class="sofy-hero">'
            . '<h1 class="sofy-hero-title">' . $this->e($this->title) . '</h1>'
            . $sub
            . $actions
            . '</div>';
    }
}
