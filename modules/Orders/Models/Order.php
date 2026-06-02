<?php

declare(strict_types=1);

namespace Orders\Models;

use Sofy\Database\Model;
use Sofy\Database\Relations\HasMany;

/**
 * Order — top-level model for a customer purchase. Aggregate root for
 * its OrderItem children. Status transitions are tracked via the
 * `status` column (see config('orders.statuses') for the allowed set).
 *
 * @property int    $id
 * @property string $number
 * @property string $customer_name
 * @property string $customer_email
 * @property string $status
 * @property float  $total
 * @property string $currency
 * @property string|null $notes
 */
class Order extends Model
{
    protected static string $table = 'orders';

    protected static array $fillable = [
        'number',
        'customer_name',
        'customer_email',
        'status',
        'total',
        'currency',
        'notes',
    ];

    protected static array $casts = [
        'total' => 'float',
    ];

    public const string STATUS_PENDING   = 'pending';
    public const string STATUS_PAID      = 'paid';
    public const string STATUS_SHIPPED   = 'shipped';
    public const string STATUS_COMPLETED = 'completed';
    public const string STATUS_CANCELLED = 'cancelled';

    /** All admissible status values — duplicated from config so model code can validate cheaply. */
    public const array STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_SHIPPED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    /** Items relation — one Order has many OrderItem rows linked by order_id. */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    /**
     * Recalculate $total from items and persist. Cheap enough to call
     * after every items mutation in the admin flow.
     */
    public function recalcTotal(): void
    {
        $sum = 0.0;
        foreach ($this->items()->get() as $item) {
            /** @var OrderItem $item */
            $sum += (float) $item->total;
        }
        $this->total = round($sum, 2);
        $this->save();
    }

    /**
     * Generate the next human-friendly order number. Looks at the highest
     * existing numeric suffix to avoid collisions on retries. Format:
     * `<prefix><6-digit-zero-padded>` — e.g. ORD-000123.
     */
    public static function generateNumber(string $prefix = 'ORD-'): string
    {
        $last = static::query()
            ->where('number', 'LIKE', $prefix . '%')
            ->orderBy('id', 'DESC')
            ->first();

        $next = 1;
        if ($last !== null && preg_match('/(\d+)$/', (string) $last->number, $m)) {
            $next = ((int) $m[1]) + 1;
        }
        return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
