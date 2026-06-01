<?php

declare(strict_types=1);

namespace Sofy\Admin;

/**
 * A single item in the admin sidebar menu. Modules build these via
 * Admin::menu()->add(...) inside their Module::register() hook.
 *
 *   Admin::menu()->add('blog.posts', 'Posts', '/admin/blog/posts')
 *       ->icon('📝')
 *       ->section('Content')
 *       ->order(10)
 *       ->badge(fn() => Post::draft()->count())
 *       ->visibleIf(fn() => auth()->user()?->hasRole('editor'));
 */
class MenuItem
{
    public string $icon    = '';
    public string $section = 'main';
    public int    $order   = 100;
    /** @var string|callable|null  Rendered next to the label; callable resolved at render time. */
    public mixed $badge    = null;
    /** @var callable|null  When set, item is omitted unless the callback returns true. */
    private mixed $visibleIf = null;

    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $url,
    ) {}

    public function icon(string $icon): static    { $this->icon = $icon;       return $this; }
    public function section(string $section): static { $this->section = $section; return $this; }
    public function order(int $order): static     { $this->order = $order;     return $this; }
    public function badge(string|callable $badge): static { $this->badge = $badge; return $this; }
    public function visibleIf(callable $check): static    { $this->visibleIf = $check; return $this; }

    public function isVisible(): bool
    {
        return $this->visibleIf === null ? true : (bool) ($this->visibleIf)();
    }

    public function badgeValue(): ?string
    {
        if ($this->badge === null) return null;
        $val = is_callable($this->badge) ? ($this->badge)() : $this->badge;
        $val = (string) $val;
        return $val === '' || $val === '0' ? null : $val;
    }

    /**
     * Highlight rule: exact match OR currentPath is a sub-path of url. So
     * /admin/users/42/edit highlights the "Users" item registered at /admin/users.
     */
    public function isActive(string $currentPath): bool
    {
        $url = rtrim($this->url, '/');
        $cur = rtrim($currentPath, '/');
        return $cur === $url || str_starts_with($cur . '/', $url . '/');
    }
}
