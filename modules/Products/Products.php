<?php

declare(strict_types=1);

namespace Products;

use Products\Admin\Widgets\ProductsCountWidget;
use Sofy\Admin\Admin;
use Sofy\Core\Application;
use Sofy\Core\Module;
use Sofy\View\Icons;

/**
 * Products — товарный каталог. Самодостаточный модуль: модель, миграция,
 * админский CRUD, виджет на дашборде, sofy-module.json.
 *
 * Интеграция с Orders: модуль Orders проверяет наличие класса
 * \Products\Models\Product перед тем как показать выпадающий список
 * товаров в форме «добавить позицию». Если Products не установлен,
 * Orders спокойно работает в режиме свободного ввода имени/цены.
 */
class Products extends Module
{
    public function name(): string
    {
        return 'products';
    }

    public function config(): array
    {
        return require $this->path('config.php');
    }

    public function register(Application $app): void
    {
        Admin::menu()->add('products', 'Товары', '/admin/products')
            ->icon(Icons::PACKAGE)
            ->section('Каталог')
            ->order(20)
            ->badge(static function (): string {
                try {
                    return (string) \Products\Models\Product::query()->where('active', true)->count();
                } catch (\Throwable) {
                    return '';
                }
            });

        Admin::widget(ProductsCountWidget::class);
    }
}
