<?php

declare(strict_types=1);

namespace Orders;

use Orders\Admin\Widgets\OrdersRevenueWidget;
use Orders\Admin\Widgets\OrdersTodayWidget;
use Sofy\Admin\Admin;
use Sofy\Core\Application;
use Sofy\Core\Module;
use Sofy\View\Icons;

/**
 * Orders — a self-contained Sofy module for managing customer orders.
 *
 * Everything the feature needs lives under modules/Orders/:
 *  - Models/Order, Models/OrderItem      ActiveRecord + items relation
 *  - Migrations/                          two migrations, auto-discovered
 *  - Controllers/Admin/OrdersController   full CRUD + quick status change
 *  - Admin/Widgets/                       two dashboard widgets
 *  - routes.php                           /admin/orders/* under EnsureAdmin
 *  - config.php                           statuses, currency, paginate
 *
 * Dropping the directory is enough — auto-discovery wires routes and
 * migrations; this class hooks the menu item and widgets into the
 * built-in admin from register().
 */
class Orders extends Module
{
    public function name(): string
    {
        return 'orders';
    }

    public function config(): array
    {
        return require $this->path('config.php');
    }

    public function register(Application $app): void
    {
        // Menu entry — sits in a "Catalog" section so a future Products
        // module can land next to it. Lazy badge counts pending orders so
        // the operator sees backlog at a glance from the sidebar.
        Admin::menu()->add('orders', 'Заказы', '/admin/orders')
            ->icon(Icons::SHOPPING_CART)
            ->section('Каталог')
            ->order(10)
            ->badge(static function (): string {
                try {
                    return (string) \Orders\Models\Order::where('status', 'pending')->count();
                } catch (\Throwable) {
                    return '';
                }
            });

        // Dashboard widgets — both 1-col so they pack alongside the stock
        // tiles in the 4-column dashboard grid.
        Admin::widget(OrdersTodayWidget::class);
        Admin::widget(OrdersRevenueWidget::class);
    }
}
