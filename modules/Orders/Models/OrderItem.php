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
 * Optional integration with the Products module: when `product_id` is
 * set, the item was sourced from the catalogue. The snapshot of `name`
 * and `unit_price` is still authoritative — deleting the Product or
 * editing its price doesn't rewrite history.
 *
 * @property int      $id
 * @property int      $order_id
 * @property int|null $product_id
 * @property string   $name
 * @property int      $quantity
 * @property float    $unit_price
 * @property float    $total
 */
class OrderItem extends Model
{
    protected static string $table = 'order_items';

    protected static array $fillable = [
        'order_id',
        'product_id',
        'name',
        'quantity',
        'unit_price',
        'total',
    ];

    protected static array $casts = [
        'order_id'   => 'int',
        'product_id' => 'int',
        'quantity'   => 'int',
        'unit_price' => 'float',
        'total'      => 'float',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Resolves the linked Product if the Products module is installed AND
     * a product_id is set. Returns null when the integration isn't
     * available or this is a free-form item — so the Orders module never
     * hard-depends on Products being present.
     */
    public function product(): ?object
    {
        if ($this->product_id === null) return null;
        if (!class_exists(\Products\Models\Product::class)) return null;
        try {
            return \Products\Models\Product::find((int) $this->product_id);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Recompute the row total from quantity × unit_price; caller still saves. */
    public function syncTotal(): void
    {
        $this->total = round(((int) $this->quantity) * ((float) $this->unit_price), 2);
    }
}
