<?php

declare(strict_types=1);

namespace Sofy\Admin;

/**
 * Singleton registry behind the Admin facade. Holds menu items, dashboard
 * widgets and small bits of admin-wide state (brand text, auth toggle).
 *
 * Not constructed directly — go through Admin::menu(), Admin::widget(),
 * Admin::panel().
 */
class AdminPanel
{
    private static ?AdminPanel $instance = null;

    /** @var array<string, MenuItem>  keyed by item->key for stable replace-by-key behaviour. */
    private array $items = [];

    /** @var list<AdminWidget> */
    private array $widgets = [];

    /** Brand HTML rendered in the sidebar header. The "<span>fy</span>" matches the framework brand pattern. */
    public string $brand = 'So<span>fy</span> Admin';

    /** Path the EnsureAdmin middleware redirects to when an unauthenticated user hits a protected route. */
    public string $loginUrl  = '/admin/login';
    public string $logoutUrl = '/admin/logout';

    /**
     * Whether EnsureAdmin requires an authenticated user before serving
     * any /admin route.
     *
     * Default ON since v0.4.13. Previously this was OFF — combined with the
     * SQL console at /admin/database/sql, that meant a fresh install shipped
     * with an internet-accessible root-level DB shell. The security audit
     * (v0.4.12) verified this on a live deployment.
     *
     * If you've already wired your own gate (e.g. nginx auth_basic, a
     * reverse-proxy ACL, or you're running purely on a closed network), set
     * this back to `false` explicitly in bootstrap/app.php:
     *
     *   \Sofy\Admin\Admin::panel()->requireAuth = false;
     *
     * Otherwise: register a POST /admin/login handler and create an admin
     * user with `php sofy admin:create`.
     */
    public bool $requireAuth = true;

    /** Role checked when $requireAuth is true and the user model uses HasRoles. */
    public string $requiredRole = 'admin';

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /** Reset — useful for tests; not for production code. */
    public static function reset(): void
    {
        self::$instance = null;
    }

    // ── Registration ──────────────────────────────────────────────────────────

    public function addMenuItem(MenuItem $item): MenuItem
    {
        $this->items[$item->key] = $item;
        return $item;
    }

    public function addWidget(AdminWidget|string $widget): AdminWidget
    {
        $w = is_string($widget) ? new $widget() : $widget;
        if (!$w instanceof AdminWidget) {
            throw new \InvalidArgumentException('Widget must extend ' . AdminWidget::class);
        }
        $this->widgets[] = $w;
        return $w;
    }

    // ── Render helpers ────────────────────────────────────────────────────────

    /**
     * Return menu items grouped by section, sorted by order then label,
     * filtered by their visibleIf callback. Sections preserve their first-seen
     * insertion order so modules can place themselves above/below each other
     * by choosing when to register.
     *
     * @return array<string, list<MenuItem>>
     */
    public function menu(): array
    {
        $grouped = [];
        foreach ($this->items as $item) {
            if (!$item->isVisible()) {
                continue;
            }
            $grouped[$item->section][] = $item;
        }
        foreach ($grouped as &$section) {
            usort($section, static fn(MenuItem $a, MenuItem $b)
                => ($a->order <=> $b->order) ?: strcmp($a->label, $b->label));
        }
        return $grouped;
    }

    /** @return list<AdminWidget>  sorted by order. */
    public function widgets(): array
    {
        $copy = $this->widgets;
        usort($copy, static fn(AdminWidget $a, AdminWidget $b) => $a->order <=> $b->order);
        return $copy;
    }
}
