<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Stat extends Component
{
    public function __construct(
        private readonly string $label,
        private readonly mixed  $value,
        private readonly ?string $trend = null,        // '+5%' | '-3%' | null
        private readonly ?string $description = null,
    ) {}

    public function render(): string
    {
        $trend = '';
        if ($this->trend !== null) {
            $up    = str_starts_with(ltrim($this->trend), '+');
            $cls   = $up ? 'up' : 'dn';
            $arrow = $up ? '↑' : '↓';
            $trend = sprintf(
                '<div class="sofy-stat-trend %s">%s %s</div>',
                $cls, $arrow, $this->e($this->trend)
            );
        }

        $desc = $this->description !== null
            ? '<div class="sofy-stat-desc">' . $this->e($this->description) . '</div>'
            : '';

        return sprintf(
            '<div class="sofy-stat">'
            . '<div class="sofy-stat-lbl">%s</div>'
            . '<div class="sofy-stat-val">%s</div>'
            . '%s%s'
            . '</div>',
            $this->e($this->label),
            $this->digits((string) $this->value),
            $trend,
            $desc
        );
    }

    /**
     * Split the value into per-character .t-digit spans so it can ride in with
     * the transitions.dev number pop-in (the entrance is triggered on load).
     * The last two characters stagger 1×/2× behind the rest.
     */
    private function digits(string $value): string
    {
        $chars = $value === '' ? [] : mb_str_split($value);
        $n     = count($chars);
        $spans = '';
        foreach ($chars as $i => $ch) {
            $stagger = match (true) {
                $i === $n - 2 => ' data-stagger="1"',
                $i === $n - 1 => ' data-stagger="2"',
                default       => '',
            };
            $spans .= '<span class="t-digit"' . $stagger . '>' . $this->e($ch) . '</span>';
        }
        return '<span class="t-digit-group">' . $spans . '</span>';
    }
}
