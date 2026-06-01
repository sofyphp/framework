<?php

declare(strict_types=1);

namespace Sofy\Database\Schema\Grammars;

use Sofy\Database\Schema\ColumnDefinition;
use Sofy\Database\Schema\Grammar;

/**
 * PostgreSQL grammar. Translations from the framework's MySQL-flavoured
 * generic types:
 *
 *   BIGINT UNSIGNED + AUTO_INCREMENT  → BIGSERIAL
 *   INT UNSIGNED + AUTO_INCREMENT     → SERIAL
 *   BIGINT UNSIGNED (non-auto)        → BIGINT
 *   TINYINT(1)                        → BOOLEAN
 *   TINYINT                           → SMALLINT
 *   LONGTEXT / MEDIUMTEXT             → TEXT
 *   DATETIME                          → TIMESTAMP
 *   JSON                              → JSONB
 *   FLOAT                             → REAL
 *   DECIMAL(p,s)                      → NUMERIC(p,s)
 *   ENUM('a','b',...)                 → VARCHAR(255) CHECK (col IN ('a','b',...))
 *
 * UNSIGNED, AUTO_INCREMENT keyword, AFTER, COMMENT inline — none of these
 * exist in Postgres; they're dropped on the floor (COMMENT could be issued
 * via a separate COMMENT ON COLUMN statement; out of scope for now).
 */
class PgSqlGrammar extends Grammar
{
    public function quoteId(string $id): string
    {
        return '"' . str_replace('"', '""', $id) . '"';
    }

    public function tableOptions(): string { return ''; }

    public function disableFKsSql(): ?string  { return "SET session_replication_role = 'replica'"; }
    public function enableFKsSql(): ?string   { return "SET session_replication_role = 'origin'"; }
    public function listTablesSql(): string   { return "SELECT tablename FROM pg_tables WHERE schemaname = current_schema()"; }
    public function hasTableSql(): string     { return "SELECT 1 FROM pg_tables WHERE schemaname = current_schema() AND tablename = ?"; }
    public function hasColumnSql(string $table): string
    {
        return 'SELECT 1 FROM information_schema.columns '
             . 'WHERE table_schema = current_schema() AND table_name = '
             . "'" . str_replace("'", "''", $table) . "' AND column_name = ?";
    }

    public function pkInlinedInColumn(ColumnDefinition $col): bool
    {
        // SERIAL/BIGSERIAL doesn't imply PRIMARY KEY, so we add it inline
        // when the column is auto-incrementing (a sensible 99%-case for
        // Sofy migrations: `$table->id()` is THE primary key).
        return $col->isAutoIncrement();
    }

    protected function mapType(string $type, ColumnDefinition $col): string
    {
        $up = strtoupper(trim($type));

        if ($col->isAutoIncrement()) {
            return str_starts_with($up, 'BIGINT') ? 'BIGSERIAL' : 'SERIAL';
        }

        return match (true) {
            $up === 'TINYINT(1)'                                  => 'BOOLEAN',
            str_starts_with($up, 'TINYINT')                       => 'SMALLINT',
            str_starts_with($up, 'BIGINT')                        => 'BIGINT',
            str_starts_with($up, 'INT UNSIGNED')                  => 'INTEGER',
            str_starts_with($up, 'INT(') || $up === 'INT'         => 'INTEGER',
            str_starts_with($up, 'INTEGER')                       => 'INTEGER',
            str_starts_with($up, 'SMALLINT')                      => 'SMALLINT',
            $up === 'LONGTEXT' || $up === 'MEDIUMTEXT'            => 'TEXT',
            $up === 'TEXT'                                        => 'TEXT',
            $up === 'DATETIME'                                    => 'TIMESTAMP',
            $up === 'TIMESTAMP' || $up === 'DATE' || $up === 'TIME' => $up,
            $up === 'JSON'                                        => 'JSONB',
            $up === 'FLOAT'                                       => 'REAL',
            $up === 'DOUBLE'                                      => 'DOUBLE PRECISION',
            str_starts_with($up, 'DECIMAL')                       => str_replace('DECIMAL', 'NUMERIC', $up),
            str_starts_with($up, 'VARCHAR')                       => $up,
            str_starts_with($up, 'CHAR(36)')                      => 'UUID',
            str_starts_with($up, 'CHAR')                          => $up,
            str_starts_with($up, 'ENUM(')                         => $this->enumToCheck($up, $col),
            default                                                => $up,
        };
    }

    protected function columnSuffix(ColumnDefinition $col): string
    {
        // Postgres has no inline AUTO_INCREMENT / AFTER / inline COMMENT.
        return '';
    }

    protected function formatDefault(mixed $value, ColumnDefinition $col): string
    {
        if ($value === null)  return 'NULL';
        if ($value === true)  return 'TRUE';
        if ($value === false) return 'FALSE';
        if (is_int($value) || is_float($value)) return (string) $value;
        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    private function enumToCheck(string $enumType, ColumnDefinition $col): string
    {
        if (preg_match('/^ENUM\((.+)\)$/i', $enumType, $m)) {
            return 'VARCHAR(255) CHECK (' . $this->quoteId($col->name) . ' IN (' . $m[1] . '))';
        }
        return 'VARCHAR(255)';
    }
}
