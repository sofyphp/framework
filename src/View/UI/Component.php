<?php

declare(strict_types=1);

namespace Sofy\View\UI;

abstract class Component
{
    /** @var array<string,string> */
    private array $htmxAttrs = [];

    abstract public function render(): string;

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
