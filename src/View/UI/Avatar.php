<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Avatar extends Component
{
    /**
     * @param string  $name     Full name — initials are extracted automatically
     * @param string  $variant  accent|success|warning|danger|info|muted
     * @param string  $size     sm|md|lg|xl
     * @param string|null $src  Optional image URL — overrides initials
     */
    public function __construct(
        private readonly string  $name,
        private readonly string  $variant = 'accent',
        private readonly string  $size    = 'md',
        private readonly ?string $src     = null,
    ) {}

    public function render(): string
    {
        $cls = 'sofy-avatar sofy-avatar-' . $this->e($this->size)
            . ' sofy-avatar-' . $this->e($this->variant);

        if ($this->src !== null) {
            return '<img src="' . $this->e($this->src) . '" alt="' . $this->e($this->name) . '"'
                . ' class="' . $cls . '" title="' . $this->e($this->name) . '">';
        }

        return '<span class="' . $cls . '" title="' . $this->e($this->name) . '">'
            . $this->e($this->initials())
            . '</span>';
    }

    private function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($parts)) {
            return '?';
        }
        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }
        return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    }
}
