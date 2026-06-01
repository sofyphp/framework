<?php

declare(strict_types=1);

namespace Sofy\Database\Schema;

use Sofy\Database\Connection;

class Schema
{
    public static function create(string $table, callable $callback): void
    {
        $conn      = Connection::getDefault();
        $grammar   = Grammar::forConnection($conn);
        $blueprint = new Blueprint();
        $callback($blueprint);

        $conn->execute($blueprint->toCreateSql($table, $grammar));
        foreach ($blueprint->extraStatements($table, $grammar) as $sql) {
            $conn->execute($sql);
        }
    }

    /**
     * Add columns/indexes to an existing table.
     */
    public static function table(string $table, callable $callback): void
    {
        $conn      = Connection::getDefault();
        $grammar   = Grammar::forConnection($conn);
        $blueprint = new Blueprint();
        $callback($blueprint);

        $conn->execute($blueprint->toAlterAddSql($table, $grammar));
        foreach ($blueprint->extraStatements($table, $grammar) as $sql) {
            $conn->execute($sql);
        }
    }

    public static function drop(string $table): void
    {
        $conn = Connection::getDefault();
        $g    = Grammar::forConnection($conn);
        $conn->execute('DROP TABLE ' . $g->quoteId($table));
    }

    public static function dropIfExists(string $table): void
    {
        $conn = Connection::getDefault();
        $g    = Grammar::forConnection($conn);
        $conn->execute('DROP TABLE IF EXISTS ' . $g->quoteId($table));
    }

    public static function rename(string $from, string $to): void
    {
        $conn   = Connection::getDefault();
        $g      = Grammar::forConnection($conn);
        $driver = $conn->getDriverName();

        // MySQL: RENAME TABLE a TO b; PostgreSQL & SQLite: ALTER TABLE a RENAME TO b.
        $sql = $driver === 'mysql'
            ? 'RENAME TABLE ' . $g->quoteId($from) . ' TO ' . $g->quoteId($to)
            : 'ALTER TABLE ' . $g->quoteId($from) . ' RENAME TO ' . $g->quoteId($to);

        $conn->execute($sql);
    }

    public static function hasTable(string $table): bool
    {
        $conn = Connection::getDefault();
        $g    = Grammar::forConnection($conn);
        return !empty($conn->query($g->hasTableSql(), [$table]));
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $conn = Connection::getDefault();
        $g    = Grammar::forConnection($conn);
        return !empty($conn->query($g->hasColumnSql($table), [$column]));
    }

    public static function dropColumn(string $table, string|array $columns): void
    {
        $conn  = Connection::getDefault();
        $g     = Grammar::forConnection($conn);
        $cols  = (array) $columns;
        $parts = implode(', ', array_map(fn($c) => 'DROP COLUMN ' . $g->quoteId($c), $cols));
        $conn->execute('ALTER TABLE ' . $g->quoteId($table) . ' ' . $parts);
    }
}
