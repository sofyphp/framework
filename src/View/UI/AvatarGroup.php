<?php

declare(strict_types=1);

namespace Sofy\View\UI;

/**
 * Avatar / chip group with a distance-falloff hover spring (transitions.dev
 * `.t-avatar-group`). Hovering one item lifts it and gently lifts its
 * neighbours, snapping back with a bouncy spring on mouse-leave. The JS is
 * wired automatically for every `.t-avatar-group` on the page.
 *
 * Usage:
 *   echo UI::avatarGroup([
 *       UI::avatar('Alice Smith'),
 *       UI::avatar('Bob Jones', 'success'),
 *       UI::avatar('Carol Lee', 'warning'),
 *   ]);
 *
 * Works for any horizontal row — pass chips, badges or buttons just as well.
 */
class AvatarGroup extends Component
{
    /**
     * @param array<mixed> $items  Avatar components (or any renderable item)
     */
    public function __construct(private readonly array $items) {}

    public function render(): string
    {
        $out = '';
        foreach ($this->items as $item) {
            $out .= '<div class="t-avatar">' . $this->slot($item) . '</div>';
        }

        return '<div class="sofy-avatar-group t-avatar-group">' . $out . '</div>';
    }
}
