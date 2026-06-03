<?php

declare(strict_types=1);

namespace Sofy\View\UI;

/**
 * Searchable select — a text box that filters its options as you type and
 * writes the chosen value to a hidden field. The component the plain <select>
 * couldn't be: usable past a few hundred options.
 *
 * Two modes:
 *  - Local (default): you pass the options; filtering happens client-side.
 *    Great up to a few thousand rows.
 *  - Remote: ->endpoint('/admin/products/search') makes it fetch options as
 *    the user types from a route you back with Sofy\Search\Search::query().
 *    For large catalogues.
 *
 * Markup-only; the `sofyCombo` behaviour + styles live in Page (same pattern
 * as DataTable's sofyDT), so they ship once per page.
 *
 *   UI::combobox('product_id', $options, selected: $id, placeholder: 'Find a product…')
 *
 * $options is [value => label] or a list of ['value' => …, 'label' => …].
 */
final class Combobox extends Component
{
    private static int $seq = 0;

    private ?string $endpoint = null;
    private string $placeholder = 'Search…';
    private bool $required = false;

    /** @param array<int|string,mixed> $options */
    public function __construct(
        private readonly string $name,
        private readonly array $options = [],
        private readonly int|string|null $selected = null,
    ) {}

    public function placeholder(string $text): static
    {
        $this->placeholder = $text;
        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;
        return $this;
    }

    /** Switch to remote mode: fetch options from $url?q=… (JSON list of {value,label}). */
    public function endpoint(string $url): static
    {
        $this->endpoint = $url;
        return $this;
    }

    public function render(): string
    {
        $id    = 'sofy-combo-' . (++self::$seq);
        $name  = $this->e($this->name);

        // Normalize options to [value, label] pairs.
        $pairs = [];
        foreach ($this->options as $k => $v) {
            if (is_array($v)) {
                $pairs[] = [(string) ($v['value'] ?? $k), (string) ($v['label'] ?? $v['value'] ?? $k)];
            } else {
                $pairs[] = [(string) $k, (string) $v];
            }
        }

        $selectedVal   = $this->selected !== null ? (string) $this->selected : '';
        $selectedLabel = '';
        foreach ($pairs as [$val, $label]) {
            if ($val === $selectedVal) {
                $selectedLabel = $label;
                break;
            }
        }

        $opts = '';
        foreach ($pairs as [$val, $label]) {
            $sel   = $val === $selectedVal ? ' aria-selected="true"' : '';
            $opts .= '<li class="sofy-combo-opt" role="option" data-value="' . $this->e($val) . '"'
                . ' data-text="' . $this->e(mb_strtolower($label, 'UTF-8')) . '"' . $sel
                . ' onmousedown="sofyCombo.pick(event,this)">' . $this->e($label) . '</li>';
        }

        $remoteAttr = $this->endpoint !== null
            ? ' data-endpoint="' . $this->e($this->endpoint) . '"'
            : '';

        return '<div class="sofy-combo" id="' . $id . '"' . $remoteAttr . $this->hxString() . '>'
            . '<input type="hidden" name="' . $name . '" value="' . $this->e($selectedVal) . '"'
                . ($this->required ? ' data-required="1"' : '') . '>'
            . '<input type="text" class="sofy-form-ctrl sofy-combo-input" autocomplete="off"'
                . ' placeholder="' . $this->e($this->placeholder) . '"'
                . ' value="' . $this->e($selectedLabel) . '"'
                . ' role="combobox" aria-expanded="false" aria-autocomplete="list"'
                . ' oninput="sofyCombo.filter(this)" onfocus="sofyCombo.open(this)"'
                . ' onkeydown="sofyCombo.key(event,this)">'
            . '<ul class="sofy-combo-list" role="listbox">' . $opts . '</ul>'
            . '<div class="sofy-combo-empty" hidden>No matches</div>'
            . '</div>';
    }
}
