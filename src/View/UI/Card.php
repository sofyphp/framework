<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Card extends Component
{
    private mixed $headerAction = null;
    private mixed $footer       = null;

    public function __construct(
        private readonly ?string $title   = null,
        private readonly mixed   $content = null,
    ) {}

    public function action(mixed $action): static
    {
        $this->headerAction = $action;
        return $this;
    }

    public function footer(mixed $footer): static
    {
        $this->footer = $footer;
        return $this;
    }

    public function render(): string
    {
        $header = '';
        if ($this->title !== null) {
            $action = $this->headerAction !== null
                ? '<div>' . $this->slot($this->headerAction) . '</div>'
                : '';
            $header = '<div class="sofy-card-hdr">'
                . '<span class="sofy-card-title">' . $this->e($this->title) . '</span>'
                . $action
                . '</div>';
        }

        $footer = $this->footer !== null
            ? '<div class="sofy-card-ftr">' . $this->slot($this->footer) . '</div>'
            : '';

        return '<div class="sofy-card">'
            . $header
            . '<div class="sofy-card-body">' . $this->slot($this->content) . '</div>'
            . $footer
            . '</div>';
    }
}
