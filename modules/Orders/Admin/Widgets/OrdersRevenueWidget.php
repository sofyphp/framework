<?php

declare(strict_types=1);

namespace Orders\Admin\Widgets;

use Orders\Models\Order;
use Sofy\Admin\AdminWidget;
use Sofy\Database\Connection;
use Sofy\View\UI;

/**
 * Dashboard tile: today's revenue — sum of `total` across orders that
 * actually generated money (paid, shipped, completed). Same drop-to-zero
 * fallback as OrdersTodayWidget when the table is missing.
 */
class OrdersRevenueWidget extends AdminWidget
{
    public int $order = 21;
    public int $cols  = 1;

    public function render(): mixed
    {
        $earning  = [Order::STATUS_PAID, Order::STATUS_SHIPPED, Order::STATUS_COMPLETED];
        $currency = (string) config('orders.default_currency', 'USD');

        try {
            // Builder doesn't ship a sum() helper, so reach for a plain
            // SUM() query — driver-agnostic SQL works on MySQL/PG/SQLite.
            $todayStart = date('Y-m-d 00:00:00');
            $placeholders = implode(',', array_fill(0, count($earning), '?'));
            $rows = Connection::getDefault()->query(
                'SELECT COALESCE(SUM(total), 0) AS s FROM orders '
                . 'WHERE created_at >= ? AND status IN (' . $placeholders . ')',
                array_merge([$todayStart], $earning),
            );
            $total = (float) (array_values($rows[0] ?? ['s' => 0])[0] ?? 0);
        } catch (\Throwable) {
            $total = 0.0;
        }

        return UI::stat(
            'Выручка сегодня',
            number_format($total, 2, '.', ' '),
            trend: $currency,
            description: 'paid + shipped + completed',
        );
    }
}
