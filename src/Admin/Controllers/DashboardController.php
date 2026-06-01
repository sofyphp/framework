<?php

declare(strict_types=1);

namespace Sofy\Admin\Controllers;

use Sofy\Admin\Admin;
use Sofy\Admin\AdminPanel;
use Sofy\Admin\AdminWidget;
use Sofy\Http\Response;
use Sofy\View\UI;

class DashboardController
{
    public function index(): Response
    {
        $widgets = AdminPanel::instance()->widgets();

        $page = Admin::page('Dashboard')->header('Dashboard');

        if (empty($widgets)) {
            $page->add(
                UI::emptyState(
                    'No widgets registered',
                    'Modules add dashboard widgets via Admin::widget(MyWidget::class). Open a module class and add one to see it here.',
                    icon: '◈',
                ),
            );
            return $page->response();
        }

        // Pack widgets into rows by their $cols span. The grid has 4 logical
        // columns, so cols=1 fits 4-per-row, cols=2 fits 2-per-row, and
        // cols=4 is rendered full-width on its own line. Widgets are bucketed
        // by consecutive same-cols runs to keep insertion order predictable.
        $pending  = [];
        $pendCols = 0;

        $flush = static function () use (&$pending, &$pendCols, $page): void {
            if ($pending === []) return;
            $perRow = max(1, intdiv(4, $pendCols ?: 1));
            $page->add(UI::grid($perRow, $pending));
            $pending  = [];
            $pendCols = 0;
        };

        foreach ($widgets as $w) {
            $body = $this->renderBody($w);
            $cols = max(1, min(4, $w->cols));

            if ($cols === 4) {
                $flush();
                $page->add($body);
                continue;
            }

            if ($pendCols !== 0 && $pendCols !== $cols) {
                $flush();
            }
            $pendCols = $cols;
            $pending[] = $body;

            if (count($pending) >= intdiv(4, $cols)) {
                $flush();
            }
        }
        $flush();

        return $page->response();
    }

    /** Normalise a widget body to one renderable cell (component, string or stringified array). */
    private function renderBody(AdminWidget $w): mixed
    {
        $body = $w->render();
        if (is_object($body) || is_string($body)) {
            return $body;
        }
        return implode('', array_map(static fn($c) => (string) $c, (array) $body));
    }
}
