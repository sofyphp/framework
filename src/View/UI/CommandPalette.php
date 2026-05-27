<?php

declare(strict_types=1);

namespace Sofy\View\UI;

/**
 * ⌘K / Ctrl+K command palette overlay.
 *
 * Place once at the bottom of the page. Opens on ⌘K or Ctrl+K.
 *
 * Usage:
 *   UI::commandPalette([
 *       ['label' => 'Dashboard', 'url' => '/dashboard', 'category' => 'Navigation'],
 *       ['label' => 'New User',  'url' => '/users/new', 'category' => 'Actions', 'shortcut' => 'N'],
 *       ['label' => 'Settings',  'url' => '/settings',  'category' => 'Navigation', 'icon' => '⚙'],
 *   ])
 *
 * @param array<array{label:string, url:string, category?:string, shortcut?:string, icon?:string}> $items
 */
class CommandPalette extends Component
{
    public function __construct(
        private readonly array  $items,
        private readonly string $placeholder = 'Search commands…',
    ) {}

    public function render(): string
    {
        $html  = '<div id="sofy-cmd" class="sofy-cmd" aria-modal="true" role="dialog" hidden>';
        $html .= '<div class="sofy-cmd-backdrop" onclick="sofyCmd.close()"></div>';
        $html .= '<div class="sofy-cmd-panel">';
        $html .= '<div class="sofy-cmd-search-wrap">'
            . '<span class="sofy-cmd-search-icon">⌕</span>'
            . '<input id="sofy-cmd-input" class="sofy-cmd-input" type="text" placeholder="' . $this->e($this->placeholder) . '" oninput="sofyCmd.filter(this.value)" autocomplete="off" spellcheck="false">'
            . '<kbd class="sofy-cmd-esc" onclick="sofyCmd.close()">esc</kbd>'
            . '</div>';
        $html .= '<div class="sofy-cmd-list" id="sofy-cmd-list" role="listbox">';

        // Group items by category
        $groups = [];
        foreach ($this->items as $item) {
            $cat = $item['category'] ?? 'Commands';
            $groups[$cat][] = $item;
        }

        foreach ($groups as $cat => $items) {
            $html .= '<div class="sofy-cmd-group">';
            $html .= '<div class="sofy-cmd-group-lbl">' . $this->e((string) $cat) . '</div>';

            foreach ($items as $item) {
                $url    = $this->e((string) ($item['url']   ?? '#'));
                $label  = $this->e((string) ($item['label'] ?? ''));
                $icon   = isset($item['icon'])
                    ? '<span class="sofy-cmd-item-icon">' . $this->e((string) $item['icon']) . '</span>'
                    : '<span class="sofy-cmd-item-icon sofy-cmd-item-icon-def">›</span>';
                $sc     = isset($item['shortcut'])
                    ? '<kbd class="sofy-cmd-item-sc">' . $this->e((string) $item['shortcut']) . '</kbd>'
                    : '';
                $search = strtolower(($item['label'] ?? '') . ' ' . ($item['category'] ?? ''));

                $html .= '<a href="' . $url . '" class="sofy-cmd-item" data-search="' . $this->e($search) . '" role="option">'
                    . $icon
                    . '<span class="sofy-cmd-item-lbl">' . $label . '</span>'
                    . $sc
                    . '</a>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '<div class="sofy-cmd-footer">'
            . '<span><kbd>↵</kbd> select</span>'
            . '<span><kbd>↑↓</kbd> navigate</span>'
            . '<span><kbd>esc</kbd> close</span>'
            . '<span><kbd>⌘K</kbd> toggle</span>'
            . '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}
