<?php

declare(strict_types=1);

namespace Sofy\Admin\Controllers;

use Sofy\Admin\Admin;
use Sofy\Admin\AdminPanel;
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
        } else {
            // Group by cols-per-row using a single 4-column grid; widgets with
            // larger $cols span more cells (the existing UI grid is 4-col on
            // wide screens and collapses to 1 below 720px).
            //
            // To keep things ergonomic we put every widget in its own UI::card
            // (controllers can return raw markup if they want a full bleed).
            $cells = [];
            foreach ($widgets as $w) {
                $body  = $w->render();
                $cells[] = is_object($body) || is_string($body)
                    ? $body
                    : implode('', array_map(static fn($c) => (string) $c, (array) $body));
            }
            $page->add(UI::grid(4, $cells));
        }

        return $page->response();
    }
}
