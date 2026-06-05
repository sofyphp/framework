<?php

declare(strict_types=1);

namespace Tests\Unit;

use Products\Models\Product;
use Tests\TestCase;

/** Active-record basics via the Products model on in-memory SQLite. */
final class ModelTest extends TestCase
{
    protected function setUp(): void
    {
        $db = $this->freshDatabase();
        $db->execute('CREATE TABLE products (id INTEGER PRIMARY KEY, sku TEXT, name TEXT, price REAL, stock INTEGER, description TEXT, active INTEGER, created_at TEXT, updated_at TEXT)');
    }

    public function test_create_and_find_with_casts(): void
    {
        $p = Product::create(['sku' => 'A1', 'name' => 'Widget', 'price' => 9.5, 'stock' => 3, 'active' => true]);
        $this->assertNotNull($p->id);

        $found = Product::find((int) $p->id);
        $this->assertNotNull($found);
        $this->assertSame('Widget', $found->name);
        $this->assertSame(9.5, $found->price);   // float cast
        $this->assertSame(3, $found->stock);     // int cast
        $this->assertTrue($found->active);       // bool cast
    }

    public function test_where_query(): void
    {
        Product::create(['sku' => 'A', 'name' => 'Active', 'price' => 1, 'stock' => 0, 'active' => true]);
        Product::create(['sku' => 'B', 'name' => 'Hidden', 'price' => 1, 'stock' => 0, 'active' => false]);
        $active = Product::query()->where('active', true)->get();
        $this->assertCount(1, $active);
        $this->assertSame('Active', $active[0]->name);
    }

    public function test_update_and_delete(): void
    {
        $p = Product::create(['sku' => 'X', 'name' => 'Old', 'price' => 1, 'stock' => 0, 'active' => true]);
        $p->fill(['name' => 'New']);
        $p->save();
        $this->assertSame('New', Product::find((int) $p->id)->name);

        $p->delete();
        $this->assertNull(Product::find((int) $p->id));
    }
}
