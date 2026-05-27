<?php

declare(strict_types=1);

namespace Sofy\View\UI;

/**
 * Success check — a celebratory checkmark that fades in, rotates upright,
 * Y-bobs and stroke-draws its path. Powered by the transitions.dev
 * `.t-success-check` snippet; sizing/color live in the `.sofy-check` rules.
 *
 * Usage:
 *   echo UI::successCheck();                    // plays on load
 *   echo UI::successCheck(autoplay: false, id: 'saved');
 *   // …then trigger from JS: sofyShowCheck(document.getElementById('saved'))
 */
class SuccessCheck extends Component
{
    public function __construct(
        private readonly bool    $autoplay = true,
        private readonly ?string $id       = null,
    ) {}

    public function render(): string
    {
        $id   = $this->id !== null ? ' id="' . $this->e($this->id) . '"' : '';
        $auto = $this->autoplay ? ' data-autoplay' : '';

        return '<span class="t-success-check sofy-check"' . $id . ' data-state="out"' . $auto . ' aria-hidden="true">'
            . '<svg viewBox="0 0 48 48" fill="none">'
            . '<path d="M13 24.5 L21 32.5 L35 15.5"/>'
            . '</svg>'
            . '</span>';
    }
}
