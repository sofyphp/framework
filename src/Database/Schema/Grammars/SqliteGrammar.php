<?php

declare(strict_types=1);

namespace Sofy\Database\Schema\Grammars;

use Sofy\Database\Schema\ColumnDefinition;
use Sofy\Database\Schema\Grammar;

/**
 * SQLite grammar. SQLite has dynamic typing — almost any column type can
 * hold any value — so the translation mostly collapses sized integer /
 * text variants onto SQLite's affinity types: INTEGER, REAL, NUMERIC,
 * TEXT. Auto-increment columns must be declared INLINE as
 * `INTEGER PRIMARY KEY AUTOINCREMENT` (no separate PRIMARY KEY clause).
 */
class SqliteGrammar extends Grammar
{
    public function quoteId(string $id): string
    {
        return '"' . str_replace('"', '""', $id) . '"';
    }

    public function tableOptions(): string { return ''; }

    public function disableFKsSql(): ?string  { return 'PRAGMA foreign_keys = OFF'; }
    public function enableFKsSql(): ?string   { return 'PRAGMA foreign_keys = ON'; }
    public function listTablesSql(): string   { return "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"; }
    public function hasTableSql(): string     { return "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?"; }
    public function hasColumnSql(string $table): string
    {
        // Use a parameterised lookup against pragma_table_info() — SQLite 3.16+.
        // The :col placeholder is bound by Schema::hasColumn() exactly like
        // the other Grammars (single positional ? bound).
        $quoted = str_replace("'", "''", $table);
        return "SELECT 1 FROM pragma_table_info('" . $quoted . "') WHERE name = ?";
    }

    public function pkInlinedInColumn(ColumnDefinition $col): bool
    {
        return $col->isAutoIncrement();
    }

    protected function wantsAutoincrementKeyword(): bool
    {
        return true;
    }

    protected function mapType(string $type, ColumnDefinition $col): string
    {
        if ($col->isAutoIncrement()) {
            return 'INTEGER';
        }

        $up = strtoupper(trim($type));

        return match (true) {
            // Booleans round-trip as INTEGER (no native BOOLEAN type).
            $up === 'TINYINT(1)'                                       => 'INTEGER',

            str_starts_with($up, 'BIGINT')                             => 'INTEGER',
            str_starts_with($up, 'INT')                                => 'INTEGER',
            str_starts_with($up, 'TINYINT')                            => 'INTEGER',
            str_starts_with($up, 'SMALLINT')                           => 'INTEGER',

            $up === 'FLOAT' || $up === 'DOUBLE'                        => 'REAL',
            str_starts_with($up, 'DECIMAL')                            => 'NUMERIC',

            str_starts_with($up, 'VARCHAR') || str_starts_with($up, 'CHAR') => 'TEXT',
            $up === 'TEXT' || $up === 'LONGTEXT' || $up === 'MEDIUMTEXT'    => 'TEXT',
            $up === 'JSON'                                              => 'TEXT',
            $up === 'DATETIME' || $up === 'TIMESTAMP'
                || $up === 'DATE' || $up === 'TIME'                     => 'TEXT',
            str_starts_with($up, 'ENUM(')                               => 'TEXT',

            default                                                     => 'TEXT',
        };
    }

    protected function columnSuffix(ColumnDefinition $col): string
    {
        return '';
    }

    protected function formatDefault(mixed $value, ColumnDefinition $col): string
    {
        if ($value === null)  return 'NULL';
        if ($value === true)  return '1';
        if ($value === false) return '0';
        if (is_int($value) || is_float($value)) return (string) $value;
        return "'" . str_replace("'", "''", (string) $value) . "'";
    }
}
