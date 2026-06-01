<?php

declare(strict_types=1);

namespace Sofy\View\UI;

use Sofy\View\Icons;

/**
 * Icon — a renderable wrapper around a named SVG from \Sofy\View\Icons.
 *
 * Use the factory: UI::icon('home') / UI::icon('settings', size: 18).
 *
 * The icon inherits text colour by default (stroke="currentColor"); pass
 * a $color to override (e.g. UI::icon('alert-triangle', color: 'var(--danger)')).
 * Size in pixels controls both width and height; stroke-width tweaks line
 * thickness without re-rendering the path.
 */
class Icon extends Component
{
    public function __construct(
        private readonly string $name,
        private readonly int    $size        = 16,
        private readonly string $color       = 'currentColor',
        private readonly int    $strokeWidth = 2,
    ) {}

    public function render(): string
    {
        $svg = Icons::get($this->name);
        if ($svg === '') {
            // Unknown icon → empty span keeps surrounding layout intact and
            // makes the typo easy to spot in DevTools.
            return '<span class="sofy-icon sofy-icon-missing" data-name="' . htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8') . '"></span>';
        }

        // Resize: rewrite the FIRST width/height attribute (the <svg> root).
        // The catalog ships every icon at width="15" height="15"; we replace
        // only those, leaving any inner shape sizes intact.
        $svg = preg_replace('/\swidth="\d+(?:\.\d+)?"/',  ' width="'  . $this->size . '"',  $svg, 1) ?? $svg;
        $svg = preg_replace('/\sheight="\d+(?:\.\d+)?"/', ' height="' . $this->size . '"', $svg, 1) ?? $svg;

        if ($this->color !== 'currentColor') {
            $svg = preg_replace(
                '/\sstroke="currentColor"/',
                ' stroke="' . htmlspecialchars($this->color, ENT_QUOTES, 'UTF-8') . '"',
                $svg,
                1,
            ) ?? $svg;
        }

        if ($this->strokeWidth !== 2) {
            $svg = preg_replace('/\sstroke-width="2"/', ' stroke-width="' . $this->strokeWidth . '"', $svg, 1) ?? $svg;
        }

        return $svg;
    }
}
