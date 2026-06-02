<?php

declare(strict_types=1);

namespace Products\Admin\Widgets;

use Products\Models\Product;
use Sofy\Admin\AdminWidget;
use Sofy\View\UI;

/**
 * Dashboard tile: количество активных товаров + общее число.
 */
class ProductsCountWidget extends AdminWidget
{
    public int $order = 25;
    public int $cols  = 1;

    public function render(): mixed
    {
        try {
            $total  = (int) Product::query()->count();
            $active = (int) Product::query()->where('active', true)->count();
        } catch (\Throwable) {
            $total  = 0;
            $active = 0;
        }

        return UI::stat(
            'Товары',
            number_format($active),
            trend: $total > $active ? '+' . ($total - $active) . ' скрыто' : null,
            description: 'активные в каталоге',
        );
    }
}
