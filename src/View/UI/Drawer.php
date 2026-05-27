<?php

declare(strict_types=1);

namespace Sofy\View\UI;

/**
 * Slide-in drawer panel (right or left).
 *
 * Usage:
 *   echo Drawer::trigger('filters-drawer', 'Filters');
 *   echo UI::drawer('filters-drawer', 'Filters', $content, position: 'right');
 */
class Drawer extends Component
{
    /**
     * @param string $position right|left
     */
    public function __construct(
        private readonly string $id,
        private readonly string $title,
        private readonly mixed  $content,
        private readonly mixed  $footer   = null,
        private readonly string $position = 'right',
        private readonly string $width    = '380px',
    ) {}

    public function render(): string
    {
        $footer = '';
        if ($this->footer !== null) {
            $footer = '<div class="sofy-drawer-ftr">' . $this->slot($this->footer) . '</div>';
        }

        $id    = $this->e($this->id);
        $style = 'width:' . $this->e($this->width);

        return '<div id="' . $id . '" class="sofy-drawer sofy-drawer-' . $this->e($this->position) . '">'
            . '<div class="sofy-drawer-backdrop" onclick="sofyDrawer.close(\'' . $id . '\')"></div>'
            . '<div class="sofy-drawer-panel" style="' . $style . '">'
            . '<div class="sofy-drawer-hdr">'
            . '<span class="sofy-drawer-title">' . $this->e($this->title) . '</span>'
            . '<button class="sofy-drawer-close" onclick="sofyDrawer.close(\'' . $id . '\')" aria-label="Close">✕</button>'
            . '</div>'
            . '<div class="sofy-drawer-body">' . $this->slot($this->content) . '</div>'
            . $footer
            . '</div>'
            . '</div>';
    }

    /**
     * Render a button that opens a drawer.
     *
     * @param 'primary'|'ghost'|'warning'|'danger'|'success' $variant
     * @param 'sm'|'md'|'lg'                                 $size
     */
    public static function trigger(string $drawerId, string $label, string $variant = 'ghost', string $size = 'md'): string
    {
        $id  = htmlspecialchars($drawerId, ENT_QUOTES, 'UTF-8');
        $cls = 'sofy-btn sofy-btn-' . htmlspecialchars($variant, ENT_QUOTES, 'UTF-8');
        if ($size !== 'md') {
            $cls .= ' sofy-btn-' . htmlspecialchars($size, ENT_QUOTES, 'UTF-8');
        }
        $lbl = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        return '<button type="button" class="' . $cls . '" onclick="sofyDrawer.open(\'' . $id . '\')">' . $lbl . '</button>';
    }
}
