<?php

declare(strict_types=1);

namespace Products\Models;

use Sofy\Database\Model;

/**
 * @property int    $id
 * @property string $sku
 * @property string $name
 * @property float  $price
 * @property int    $stock
 * @property string|null $description
 * @property bool   $active
 */
class Product extends Model
{
    protected static string $table = 'products';

    protected static array $fillable = [
        'sku',
        'name',
        'price',
        'stock',
        'description',
        'active',
    ];

    protected static array $casts = [
        'price'  => 'float',
        'stock'  => 'int',
        'active' => 'bool',
    ];

    /**
     * Generate the next unique SKU. Mirrors Order::generateNumber() —
     * looks at the highest existing numeric suffix to keep sequence
     * stable on retries.
     */
    public static function generateSku(string $prefix = 'SKU-'): string
    {
        $last = static::query()
            ->where('sku', 'LIKE', $prefix . '%')
            ->orderBy('id', 'DESC')
            ->first();

        $next = 1;
        if ($last !== null && preg_match('/(\d+)$/', (string) $last->sku, $m)) {
            $next = ((int) $m[1]) + 1;
        }
        return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
