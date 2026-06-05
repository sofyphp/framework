<?php

declare(strict_types=1);

namespace Sofy\View\UI;

abstract class Component
{
    /** @var array<string,string> */
    private array $htmxAttrs = [];

    /** Per-instance accent color, exposed to CSS as the `--c` custom property. */
    protected ?string $color = null;

    abstract public function render(): string;

    /**
     * Override this component's accent color for one instance instead of the
     * theme default. Components that support it read `var(--c, …)` in their CSS
     * — so the same color produces the right fill/border/text automatically.
     *
     *   UI::badge('VIP')->color('#7c5cff')
     *   UI::button('Delete', '#')->color('crimson')
     *   UI::progress(70)->color('var(--accent2)')
     *
     * Accepts hex (#rgb/#rrggbb/#rrggbbaa), rgb()/rgba()/hsl()/hsla(),
     * var(--name) or a CSS color keyword. Anything else is ignored (the style
     * attribute is injection-safe).
     */
    public function color(?string $color): static
    {
        $this->color = $this->sanitizeColor($color);
        return $this;
    }

    /**
     * Inline ` style="--c:<color>"` for the root element when a color is set,
     * otherwise ''. Merge into an existing style with colorStyleInto().
     */
    protected function colorAttr(): string
    {
        return $this->color !== null ? ' style="--c:' . $this->color . '"' : '';
    }

    /** Prefix `--c:<color>;` for inlining into an element that already has a style. */
    protected function colorDecl(): string
    {
        return $this->color !== null ? '--c:' . $this->color . ';' : '';
    }

    /**
     * Whitelist safe CSS color tokens. This value lands in a style attribute,
     * so only well-formed colors are allowed — nothing that could carry `;`,
     * `}`, quotes or other style-breaking characters.
     */
    protected function sanitizeColor(?string $color): ?string
    {
        if ($color === null) {
            return null;
        }
        $color = trim($color);
        if ($color === '') {
            return null;
        }
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
            return $color;
        }
        if (preg_match('/^(rgb|rgba|hsl|hsla)\([0-9.,%\/\s]+\)$/i', $color)) {
            return $color;
        }
        if (preg_match('/^var\(--[a-zA-Z0-9_-]+\)$/', $color)) {
            return $color;
        }
        if (preg_match('/^[a-zA-Z]{3,20}$/', $color)) {
            return strtolower($color); // CSS color keyword (tomato, rebeccapurple…)
        }
        return null;
    }

    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Attach an HTMX attribute to this component's root element.
     *
     *   UI::button('Load', '#')
     *       ->hx('hx-get', '/api/users')
     *       ->hx('hx-target', '#user-list')
     *       ->hx('hx-swap', 'innerHTML')
     */
    public function hx(string $attr, string $value): static
    {
        $this->htmxAttrs[$attr] = $value;
        return $this;
    }

    /**
     * Attach multiple HTMX attributes at once.
     *
     *   ->hxAttrs(['hx-get' => '/users', 'hx-target' => '#list'])
     *
     * @param array<string,string> $attrs
     */
    public function hxAttrs(array $attrs): static
    {
        foreach ($attrs as $key => $value) {
            $this->htmxAttrs[$key] = $value;
        }
        return $this;
    }

    /** Build the hx-* attribute string for inclusion in root element tags. */
    protected function hxString(): string
    {
        $out = '';
        foreach ($this->htmxAttrs as $key => $value) {
            $out .= ' ' . $this->e($key) . '="' . $this->e($value) . '"';
        }
        return $out;
    }

    protected function e(mixed $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Render a slot: string, Component, or array of Components. */
    protected function slot(mixed $content): string
    {
        if ($content === null) return '';
        if (is_array($content)) return implode('', array_map(fn($c) => (string) $c, $content));
        return (string) $content;
    }
}
