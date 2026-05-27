<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Heading extends Component
{
    public function __construct(
        private readonly string $text,
        private readonly int    $level = 2,
    ) {}

    public function render(): string
    {
        $n = max(1, min(6, $this->level));
        return "<h$n class=\"sofy-h sofy-h$n\">" . $this->e($this->text) . "</h$n>";
    }
}
