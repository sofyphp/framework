<?php

declare(strict_types=1);

use Sofy\Database\Connection;

/**
 * Integration migration: Orders ↔ Products.
 *
 * Adds a nullable product_id column to order_items so an Order's line
 * item can optionally reference a Product in the catalog. The column is
 * nullable because Orders is independent of Products — manual free-form
 * items still work without any Product row.
 *
 * No FK constraint is declared: keeps the migration driver-agnostic and
 * means you can delete a Product without losing historical order items
 * (the snapshot of name + unit_price stays valid).
 */
return new class {
    public function up(): void
    {
        $db = Connection::getDefault();

        // Skip if already added (idempotent — protects rerun on broken installs)
        if ($this->columnExists($db, 'order_items', 'product_id')) {
            return;
        }

        $db->execute('ALTER TABLE order_items ADD COLUMN product_id BIGINT NULL');

        // Driver-aware index creation. SQLite + MySQL accept a separate
        // CREATE INDEX, PostgreSQL too — but the IF NOT EXISTS variant is
        // safe across all three.
        try {
            $db->execute('CREATE INDEX IF NOT EXISTS idx_order_items_product_id ON order_items(product_id)');
        } catch (\Throwable) {
            // Older MySQL versions don't support IF NOT EXISTS on CREATE INDEX
            try {
                $db->execute('CREATE INDEX idx_order_items_product_id ON order_items(product_id)');
            } catch (\Throwable) {
                // Index probably exists; not fatal.
            }
        }
    }

    public function down(): void
    {
        $db = Connection::getDefault();
        try {
            $db->execute('DROP INDEX IF EXISTS idx_order_items_product_id ON order_items');
        } catch (\Throwable) {
            try { $db->execute('DROP INDEX idx_order_items_product_id ON order_items'); } catch (\Throwable) {}
        }
        try {
            $db->execute('ALTER TABLE order_items DROP COLUMN product_id');
        } catch (\Throwable) {}
    }

    private function columnExists(Connection $db, string $table, string $column): bool
    {
        try {
            // Cheap fallback: try to SELECT the column.
            $db->query("SELECT $column FROM $table LIMIT 0");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
};
