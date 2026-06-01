<?php

declare(strict_types=1);

namespace Sofy\Admin;

/**
 * Dashboard widget. Modules register concrete subclasses via
 * Admin::widget(MyWidget::class) — the AdminPanel sorts by $order and
 * renders them in a CSS grid on /admin (dashboard).
 *
 * Example:
 *
 *   class UsersCountWidget extends \Sofy\Admin\AdminWidget
 *   {
 *       public int $order = 10;
 *       public int $cols  = 1;
 *
 *       public function render(): mixed
 *       {
 *           return \Sofy\View\UI::stat('Users', \Main\Models\User::count(), '+12%');
 *       }
 *   }
 *
 *   // somewhere in Module::register():
 *   \Sofy\Admin\Admin::widget(UsersCountWidget::class);
 */
abstract class AdminWidget
{
    /** Sort order inside the dashboard grid (smaller first). */
    public int $order = 100;

    /** How many columns of the dashboard grid this widget spans (1 — quarter, 4 — full row). */
    public int $cols = 1;

    /**
     * Return the widget body — either a UI Component, an HTML string, or
     * an array of components (combined together).
     */
    abstract public function render(): mixed;
}
