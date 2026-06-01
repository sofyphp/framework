<?php

declare(strict_types=1);

namespace Sofy\Admin;

use Sofy\Http\Response;
use Sofy\View\UI;

/**
 * Renders the admin chrome — fixed sidebar built from registered MenuItems,
 * a top bar with the page title and theme toggle, and a content slot for the
 * controller's components. Reuses UI\Page for the <head>, theme/script bones
 * and the soft visual identity; the admin-specific layout sits on top via a
 * single big UI::raw() block + a small CSS append.
 *
 * Usage from a controller:
 *
 *   return Admin::page('Users')
 *       ->header('All users', UI::button('+ New', '/admin/users/create', 'primary'))
 *       ->add(
 *           UI::card(null, UI::dataTable($headers, $rows)),
 *       )
 *       ->response();
 */
class AdminPage
{
    private array  $components    = [];
    private ?string $headerTitle  = null;
    private mixed   $headerActions = null;

    public function __construct(private readonly string $title) {}

    public function add(mixed ...$components): static
    {
        array_push($this->components, ...$components);
        return $this;
    }

    /** Page-level header — the line under the topbar with the section title + action buttons. */
    public function header(string $title, mixed $actions = null): static
    {
        $this->headerTitle   = $title;
        $this->headerActions = $actions;
        return $this;
    }

    public function response(int $status = 200): Response
    {
        return new Response($this->render(), $status);
    }

    public function render(): string
    {
        $panel       = AdminPanel::instance();
        $currentPath = $this->currentPath();

        $sidebar    = $this->renderSidebar($panel, $currentPath);
        $topbar     = $this->renderTopbar();
        $pageHeader = $this->renderPageHeader();
        $body       = implode('', array_map(static fn($c) => (string) $c, $this->components));

        $shell = '<div class="sofy-admin">'
            . $sidebar
            . '<main class="sofy-admin-main">'
            . $topbar
            . '<div class="sofy-admin-body">'
            . $pageHeader
            . $body
            . '</div>'
            . '</main>'
            . '</div>';

        return UI::page($this->title)
            ->themeToggle()
            ->localeSwitcher()
            ->moon(false)
            ->footer(false)
            ->css($this->css())
            ->add(UI::raw($shell))
            ->render();
    }

    // ── Chrome pieces ─────────────────────────────────────────────────────────

    private function renderSidebar(AdminPanel $panel, string $currentPath): string
    {
        $sections = $panel->menu();

        $out = '<aside class="sofy-admin-sidebar">'
             . '<a class="sofy-admin-brand" href="/admin">' . $panel->brand . '</a>'
             . '<nav class="sofy-admin-nav">';

        // Always-on "Dashboard" entry, plus a divider before the first dynamic section.
        $out .= $this->renderItemRaw('Dashboard', '/admin', '◈', $currentPath === '/admin' || $currentPath === '/admin/');

        if (!empty($sections)) {
            $out .= '<div class="sofy-admin-divider"></div>';
        }

        foreach ($sections as $sectionName => $items) {
            // 'main' is the implicit section — emit without a heading. Any
            // named section ('Manage', 'Content', …) gets a small label.
            if ($sectionName !== 'main') {
                $out .= '<div class="sofy-admin-section-label">' . htmlspecialchars($sectionName, ENT_QUOTES, 'UTF-8') . '</div>';
            }
            foreach ($items as $item) {
                $out .= $this->renderMenuItem($item, $currentPath);
            }
        }

        $out .= '</nav></aside>';
        return $out;
    }

    private function renderMenuItem(MenuItem $item, string $currentPath): string
    {
        $badge = $item->badgeValue();
        return $this->renderItemRaw(
            $item->label,
            $item->url,
            $item->icon,
            $item->isActive($currentPath),
            $badge,
        );
    }

    private function renderItemRaw(string $label, string $url, string $icon, bool $active, ?string $badge = null): string
    {
        $cls = 'sofy-admin-item' . ($active ? ' active' : '');
        $iconHtml = $icon !== '' ? '<span class="sofy-admin-icon">' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '</span>' : '';
        $badgeHtml = $badge !== null
            ? '<span class="sofy-admin-badge">' . htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') . '</span>'
            : '';
        return '<a class="' . $cls . '" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
             . $iconHtml
             . '<span class="sofy-admin-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
             . $badgeHtml
             . '</a>';
    }

    private function renderTopbar(): string
    {
        return '<header class="sofy-admin-topbar">'
             . '<div class="sofy-admin-topbar-title">' . htmlspecialchars($this->title, ENT_QUOTES, 'UTF-8') . '</div>'
             . '<div class="sofy-admin-topbar-actions"></div>'
             . '</header>';
    }

    private function renderPageHeader(): string
    {
        if ($this->headerTitle === null) {
            return '';
        }
        $actions = $this->headerActions !== null
            ? '<div class="sofy-admin-page-actions">' . $this->slotToString($this->headerActions) . '</div>'
            : '';
        return '<div class="sofy-admin-page-header">'
             . '<h1 class="sofy-admin-page-title">' . htmlspecialchars($this->headerTitle, ENT_QUOTES, 'UTF-8') . '</h1>'
             . $actions
             . '</div>';
    }

    private function slotToString(mixed $value): string
    {
        if ($value === null)    return '';
        if (is_array($value))   return implode('', array_map(static fn($v) => (string) $v, $value));
        return (string) $value;
    }

    private function currentPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $qs  = strpos($uri, '?');
        return $qs === false ? $uri : substr($uri, 0, $qs);
    }

    // ── CSS ───────────────────────────────────────────────────────────────────

    private function css(): string
    {
        return <<<'CSS'
        /* Escape sofy-main's centered max-width / padding — admin layout is full-bleed. */
        .sofy-main:has(.sofy-admin){max-width:none;padding:0;margin:0}

        .sofy-admin{
            display:flex;min-height:100vh;
            background:var(--bg);color:var(--text);font-family:var(--font);
        }

        /* ── Sidebar ─────────────────────────────────────────────────────────── */
        .sofy-admin-sidebar{
            flex:0 0 240px;width:240px;
            background:var(--surf);border-right:1px solid var(--border);
            display:flex;flex-direction:column;
            position:sticky;top:0;height:100vh;overflow-y:auto;
            padding:18px 12px;
            box-shadow:var(--shadow);
        }
        .sofy-admin-brand{
            display:block;font-weight:800;font-size:18px;letter-spacing:-.02em;
            color:var(--bright);text-decoration:none;
            padding:8px 12px 22px;margin-bottom:8px;
        }
        .sofy-admin-brand span{color:var(--accent)}
        .sofy-admin-nav{display:flex;flex-direction:column;gap:2px}
        .sofy-admin-divider{height:1px;background:var(--border);margin:10px 4px}
        .sofy-admin-section-label{
            font-size:10px;letter-spacing:.08em;text-transform:uppercase;
            color:var(--muted);font-weight:600;
            padding:14px 12px 6px;
        }
        .sofy-admin-item{
            display:flex;align-items:center;gap:10px;
            padding:9px 12px;border-radius:10px;
            color:var(--text);text-decoration:none;font-size:13px;
            transition:background var(--t),color var(--t);
        }
        .sofy-admin-item:hover{background:rgba(0,0,0,.03);color:var(--bright)}
        .sofy-admin-item.active{
            background:rgba(217,119,87,.12);color:var(--accent);font-weight:600;
        }
        [data-theme="dark"] .sofy-admin-item:hover{background:rgba(255,255,255,.04)}
        .sofy-admin-icon{font-size:14px;width:18px;text-align:center;flex-shrink:0;opacity:.85}
        .sofy-admin-label{flex:1}
        .sofy-admin-badge{
            font-size:10px;font-weight:700;letter-spacing:.04em;
            background:var(--accent);color:#fff;
            padding:2px 7px;border-radius:100px;line-height:1;
        }

        /* ── Main column ─────────────────────────────────────────────────────── */
        .sofy-admin-main{flex:1;min-width:0;display:flex;flex-direction:column}
        .sofy-admin-topbar{
            position:sticky;top:0;z-index:5;
            display:flex;align-items:center;gap:16px;
            padding:14px 32px;
            border-bottom:1px solid var(--border);
            background:rgba(255,250,245,.82);backdrop-filter:blur(10px) saturate(1.4);
        }
        [data-theme="dark"] .sofy-admin-topbar{background:rgba(26,22,19,.82)}
        .sofy-admin-topbar-title{font-size:13px;color:var(--muted);letter-spacing:.04em}
        .sofy-admin-topbar-actions{margin-left:auto;display:flex;align-items:center;gap:8px}
        .sofy-admin-body{padding:28px 32px;max-width:1280px;width:100%}
        .sofy-admin-page-header{
            display:flex;align-items:center;justify-content:space-between;gap:16px;
            margin-bottom:24px;
        }
        .sofy-admin-page-title{font-size:22px;font-weight:700;color:var(--bright);letter-spacing:-.02em}
        .sofy-admin-page-actions{display:flex;gap:8px;align-items:center}

        /* ── Mobile ──────────────────────────────────────────────────────────── */
        @media(max-width:760px){
            .sofy-admin{flex-direction:column}
            .sofy-admin-sidebar{position:static;height:auto;width:100%;flex:none;
                border-right:none;border-bottom:1px solid var(--border)}
            .sofy-admin-nav{flex-direction:row;flex-wrap:wrap;gap:6px}
            .sofy-admin-section-label{display:none}
            .sofy-admin-body{padding:18px 16px}
            .sofy-admin-topbar{padding:12px 16px}
        }
        CSS;
    }
}
