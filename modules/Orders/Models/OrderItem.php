<?php

declare(strict_types=1);

namespace Orders\Models;

use Sofy\Database\Model;
use Sofy\Database\Relations\BelongsTo;

/**
 * Line item on an Order. Each row stores its own snapshot of name +
 * unit price so historic orders don't change when product catalogue
 * does. `total` is auto-computed in setQuantity/setUnitPrice but is
 * also stored so admin queries (sums, exports) stay simple.
 *
 * @property int    $id
 * @property int    $order_id
 * @property string $name
 * @property int    $quantity
 * @property float  $unit_price
 * @property float  $total
 */
class OrderItem extends Model
{
    protected static string $table = 'order_items';

    protected static array $fillable = [
        'order_id',
        'name',
        'quantity',
        'unit_price',
        'total',
    ];

    protected static array $casts = [
        'order_id'   => 'int',
        'quantity'   => 'int',
        'unit_price' => 'float',
        'total'      => 'float',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /** Recompute the row total from quantity × unit_price; caller still saves. */
    public function syncTotal(): void
    {
        $this->total = round(((int) $this->quantity) * ((float) $this->unit_price), 2);
    }
}
