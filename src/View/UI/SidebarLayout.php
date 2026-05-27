<?php

declare(strict_types=1);

namespace Sofy\View\UI;

/**
 * Two-column layout with a sidebar.
 *
 * Usage:
 *   UI::sidebarLayout(
 *       sidebar: UI::card('Nav', $navList),
 *       content: UI::card('Details', $mainContent),
 *       width: '220px',
 *       position: 'left',
 *   )
 *
 * @param string $position left|right
 */
class SidebarLayout extends Component
{
    public function __construct(
        private readonly mixed  $sidebar,
        private readonly mixed  $content,
        private readonly string $width    = '240px',
        private readonly string $position = 'left',
        private readonly string $gap      = '20px',
    ) {}

    public function render(): string
    {
        $sideStyle = 'flex-shrink:0;width:' . $this->e($this->width);
        $wrapStyle = 'gap:' . $this->e($this->gap);

        $side = '<div class="sofy-sl-sidebar" style="' . $sideStyle . '">' . $this->slot($this->sidebar) . '</div>';
        $main = '<div class="sofy-sl-main">' . $this->slot($this->content) . '</div>';

        $children = $this->position === 'right' ? $main . $side : $side . $main;

        return '<div class="sofy-sl" style="' . $wrapStyle . '">' . $children . '</div>';
    }
}
