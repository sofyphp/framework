<?php

declare(strict_types=1);

/**
 * Orders module config — accessible via config('orders.*') once the
 * module's name() returns 'orders'.
 */
return [
    'statuses' => [
        'pending'   => 'Ожидает оплаты',
        'paid'      => 'Оплачен',
        'shipped'   => 'Отправлен',
        'completed' => 'Завершён',
        'cancelled' => 'Отменён',
    ],
    'status_variants' => [
        'pending'   => 'warning',
        'paid'      => 'info',
        'shipped'   => 'accent',
        'completed' => 'success',
        'cancelled' => 'default',
    ],
    'default_currency' => 'USD',
    'per_page'         => 25,
    // Prefix used by Order::generateNumber() when creating a new order
    // without an explicit number. The full number looks like ORD-000123.
    'number_prefix'    => 'ORD-',
];
