<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Steps extends Component
{
    /**
     * @param string[] $steps   Step labels in order
     * @param int      $current 1-based index of the active step
     */
    public function __construct(
        private readonly array $steps,
        private readonly int   $current = 1,
    ) {}

    public function render(): string
    {
        $html = '<div class="sofy-steps">';
        foreach ($this->steps as $i => $label) {
            $n   = $i + 1;
            if ($n < $this->current) {
                $cls  = 'sofy-step done';
                $icon = '✓';
            } elseif ($n === $this->current) {
                $cls  = 'sofy-step active';
                $icon = (string) $n;
            } else {
                $cls  = 'sofy-step pending';
                $icon = (string) $n;
            }
            $html .= '<div class="' . $cls . '">'
                . '<div class="sofy-step-dot">' . $icon . '</div>'
                . '<div class="sofy-step-label">' . $this->e($label) . '</div>'
                . '</div>';
        }
        return $html . '</div>';
    }
}
