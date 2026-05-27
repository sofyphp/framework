<?php

declare(strict_types=1);

namespace Sofy\View\UI;

/**
 * Modal dialog — uses native <dialog> + showModal() API.
 *
 * Usage:
 *   echo Modal::trigger('confirm-modal', 'Open', 'primary');
 *   echo UI::modal('confirm-modal', 'Title', 'Body content', footer: [
 *       UI::button('Cancel', '#', 'ghost'),
 *       UI::button('Confirm', '#', 'danger'),
 *   ]);
 *
 * @param 'sm'|'md'|'lg'|'xl' $size
 */
class Modal extends Component
{
    public function __construct(
        private readonly string $id,
        private readonly string $title,
        private readonly mixed  $content,
        private readonly mixed  $footer  = null,
        private readonly string $size    = 'md',
    ) {}

    public function render(): string
    {
        $footer = '';
        if ($this->footer !== null) {
            $footer = '<div class="sofy-dialog-ftr">' . $this->slot($this->footer) . '</div>';
        }

        $id = $this->e($this->id);

        return '<dialog id="' . $id . '" class="sofy-dialog sofy-dialog-' . $this->e($this->size) . '">'
            . '<div class="sofy-dialog-form t-modal">'
            . '<div class="sofy-dialog-hdr">'
            . '<span class="sofy-dialog-title">' . $this->e($this->title) . '</span>'
            . '<button class="sofy-dialog-close" type="button" onclick="sofyModal.close(\'' . $id . '\')" aria-label="Close">✕</button>'
            . '</div>'
            . '<div class="sofy-dialog-body">' . $this->slot($this->content) . '</div>'
            . $footer
            . '</div>'
            . '</dialog>';
    }

    /**
     * Render a button that opens this modal.
     *
     * @param 'primary'|'ghost'|'warning'|'danger'|'success' $variant
     * @param 'sm'|'md'|'lg'                                 $size
     */
    public static function trigger(string $modalId, string $label, string $variant = 'primary', string $size = 'md'): string
    {
        $id  = htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8');
        $cls = 'sofy-btn sofy-btn-' . htmlspecialchars($variant, ENT_QUOTES, 'UTF-8');
        if ($size !== 'md') {
            $cls .= ' sofy-btn-' . htmlspecialchars($size, ENT_QUOTES, 'UTF-8');
        }
        $lbl = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        return '<button type="button" class="' . $cls . '" onclick="sofyModal.open(\'' . $id . '\')">' . $lbl . '</button>';
    }
}
