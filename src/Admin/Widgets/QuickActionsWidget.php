<?php

declare(strict_types=1);

namespace Sofy\Admin\Widgets;

use Sofy\Admin\AdminWidget;
use Sofy\View\Icons;
use Sofy\View\UI;

/**
 * Full-width card with one-click jumps to the most-used admin pages.
 * The icons come from the framework-wide \Sofy\View\Icons catalog and
 * inherit the page's theme via stroke="currentColor".
 */
class QuickActionsWidget extends AdminWidget
{
    public int $order = 50;
    public int $cols  = 4;

    public function render(): mixed
    {
        $actions = [
            ['users',    'Users',       '/admin/users'],
            ['database', 'Database',    '/admin/database'],
            ['terminal', 'SQL Console', '/admin/database/sql'],
            ['box',      'Modules',     '/admin/system/modules'],
            ['settings', 'System',      '/admin/system'],
        ];

        $tiles = '';
        foreach ($actions as [$icon, $label, $href]) {
            $tiles .=
                '<a class="sofy-admin-action" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
                . '<span class="sofy-admin-action-ico">' . UI::icon($icon, size: 20) . '</span>'
                . '<span class="sofy-admin-action-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
                . '</a>';
        }

        $css = <<<CSS
        <style>
            .sofy-admin-actions{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px}
            .sofy-admin-action{display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid var(--border);border-radius:10px;background:var(--surface);color:var(--text);text-decoration:none;transition:transform .12s ease,border-color .12s ease,background .12s ease}
            .sofy-admin-action:hover{transform:translateY(-1px);border-color:var(--accent);background:var(--surface-2,var(--surface))}
            .sofy-admin-action-ico{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;background:var(--accent-soft,rgba(255,107,90,.12));color:var(--accent)}
            .sofy-admin-action-label{font-weight:600;font-size:13px}
        </style>
        CSS;

        return UI::card(
            'Quick actions',
            UI::raw($css . '<div class="sofy-admin-actions">' . $tiles . '</div>'),
        );
    }
}
