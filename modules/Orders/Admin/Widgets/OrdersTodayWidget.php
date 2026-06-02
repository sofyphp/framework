<?php

declare(strict_types=1);

namespace Orders\Admin\Widgets;

use Orders\Models\Order;
use Sofy\Admin\AdminWidget;
use Sofy\View\UI;

/**
 * Dashboard tile: number of orders created today + delta vs yesterday.
 * Degrades gracefully (returns 0) when the orders table is missing —
 * /admin keeps loading even before `php sofy migrate` has been run.
 */
class OrdersTodayWidget extends AdminWidget
{
    public int $order = 20;
    public int $cols  = 1;

    public function render(): mixed
    {
        [$today, $yesterday] = $this->counts();
        $delta = $today - $yesterday;
        $trend = $delta === 0
            ? null
            : ($delta > 0 ? "+{$delta} vs вчера" : "{$delta} vs вчера");

        return UI::stat(
            'Заказы сегодня',
            number_format($today),
            trend: $trend,
            description: 'за последние 24ч',
        );
    }

    /** @return array{0:int,1:int} */
    private function counts(): array
    {
        try {
            $todayStart     = date('Y-m-d 00:00:00');
            $yesterdayStart = date('Y-m-d 00:00:00', strtotime('-1 day'));

            $today = (int) Order::query()
                ->where('created_at', '>=', $todayStart)
                ->count();

            $yesterday = (int) Order::query()
                ->where('created_at', '>=', $yesterdayStart)
                ->where('created_at', '<',  $todayStart)
                ->count();

            return [$today, $yesterday];
        } catch (\Throwable) {
            return [0, 0];
        }
    }
}
