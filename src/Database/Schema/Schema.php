<?php

declare(strict_types=1);

namespace Sofy\Database\Schema;

use Sofy\Database\Connection;

class Schema
{
    public static function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint();
        $callback($blueprint);
        Connection::getDefault()->execute($blueprint->toCreateSql($table));
    }

    /**
     * Добавляет колонки/индексы к существующей таблице.
     */
    public static function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint();
        $callback($blueprint);
        Connection::getDefault()->execute($blueprint->toAlterAddSql($table));
    }

    public static function drop(string $table): void
    {
        Connection::getDefault()->execute("DROP TABLE `$table`");
    }

    public static function dropIfExists(string $table): void
    {
        Connection::getDefault()->execute("DROP TABLE IF EXISTS `$table`");
    }

    public static function rename(string $from, string $to): void
    {
        Connection::getDefault()->execute("RENAME TABLE `$from` TO `$to`");
    }

    public static function hasTable(string $table): bool
    {
        $result = Connection::getDefault()->query('SHOW TABLES LIKE ?', [$table]);
        return !empty($result);
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $result = Connection::getDefault()->query(
            "SHOW COLUMNS FROM `$table` LIKE ?",
            [$column]
        );
        return !empty($result);
    }

    public static function dropColumn(string $table, string|array $columns): void
    {
        $cols  = (array) $columns;
        $parts = implode(', ', array_map(fn($c) => "DROP COLUMN `$c`", $cols));
        Connection::getDefault()->execute("ALTER TABLE `$table` $parts");
    }
}
