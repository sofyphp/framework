<?php

declare(strict_types=1);

namespace Sofy\Database\Schema;

use Sofy\Database\Connection;
use Sofy\Database\Schema\Grammars\MySqlGrammar;
use Sofy\Database\Schema\Grammars\PgSqlGrammar;
use Sofy\Database\Schema\Grammars\SqliteGrammar;

/**
 * Per-driver SQL grammar.
 *
 * Schema/Blueprint/QueryBuilder are written against a generic Sofy "SQL"
 * (MySQL-flavoured for historical reasons — BIGINT UNSIGNED, LONGTEXT,
 * AUTO_INCREMENT, backtick identifiers, ENGINE=InnoDB …). At SQL-emit
 * time, the Connection's Grammar translates that into the dialect the
 * actual driver speaks (PostgreSQL → BIGSERIAL, JSONB, "id"; SQLite
 * → INTEGER PRIMARY KEY AUTOINCREMENT, TEXT, …).
 */
abstract class Grammar
{
    public static function forConnection(Connection $conn): self
    {
        return self::forDriver($conn->getDriverName());
    }

    public static function forDriver(string $driver): self
    {
        return match ($driver) {
            'pgsql'  => new PgSqlGrammar(),
            'sqlite' => new SqliteGrammar(),
            default  => new MySqlGrammar(),
        };
    }

    // ── Identifier quoting ────────────────────────────────────────────────────

    /** Quote a single identifier (table or column name). */
    abstract public function quoteId(string $id): string;

    /** Quote a dotted identifier — "schema.table" / "table.column". */
    public function quoteIdPath(string $path): string
    {
        return implode('.', array_map($this->quoteId(...), explode('.', $path)));
    }

    // ── Column / table SQL ────────────────────────────────────────────────────

    /** Full SQL fragment for a column inside CREATE TABLE / ADD COLUMN. */
    public function columnSql(ColumnDefinition $col): string
    {
        $type = $this->mapType($col->type, $col);
        $sql  = $this->quoteId($col->name) . ' ' . $type;

        // SQLite encodes "PRIMARY KEY" + autoincrement directly on the column
        // (no separate PRIMARY KEY clause). We mark that here so Blueprint
        // can skip its own PRIMARY KEY line for the same column.
        if ($this->pkInlinedInColumn($col)) {
            $sql .= ' PRIMARY KEY' . ($col->isAutoIncrement() && $this->wantsAutoincrementKeyword() ? ' AUTOINCREMENT' : '');
        }

        $sql .= $col->isNullable() ? ' NULL' : ' NOT NULL';

        if ($col->hasDefault()) {
            $sql .= ' DEFAULT ' . $this->formatDefault($col->getDefault(), $col);
        }

        $sql .= $this->columnSuffix($col);
        return $sql;
    }

    /** Trailing options after the column list — MySQL's ENGINE=…, empty elsewhere. */
    abstract public function tableOptions(): string;

    /**
     * True when the column's PRIMARY KEY clause is rendered on the column line
     * itself (SQLite's INTEGER PRIMARY KEY AUTOINCREMENT, optionally PG's
     * SERIAL PRIMARY KEY). Blueprint skips its own PRIMARY KEY line then.
     */
    public function pkInlinedInColumn(ColumnDefinition $col): bool
    {
        return false;
    }

    /** SQLite needs the literal "AUTOINCREMENT" keyword; others don't. */
    protected function wantsAutoincrementKeyword(): bool
    {
        return false;
    }

    // ── Foreign-key enforcement toggle (for fresh()) ─────────────────────────

    /** SQL to suspend foreign-key checks before a fresh()-style drop sweep. */
    abstract public function disableFKsSql(): ?string;

    /** SQL to restore foreign-key checks afterwards. */
    abstract public function enableFKsSql(): ?string;

    // ── Catalog queries ──────────────────────────────────────────────────────

    /** SQL returning rows whose first column is each user table's name. */
    abstract public function listTablesSql(): string;

    /** SQL for "does table X exist?" — takes the table name as a parameter. */
    abstract public function hasTableSql(): string;

    /** SQL for "does column Y exist on table X?" — table is interpolated, column is parameter. */
    abstract public function hasColumnSql(string $table): string;

    /**
     * Return all columns of a table as a list of unified rows:
     *
     *   [ ['name' => 'id', 'type' => 'bigint', 'nullable' => false, 'default' => null], … ]
     *
     * Each driver runs its native catalog query and maps the result so callers
     * (Schema::columns(), the admin DB browser, …) don't have to.
     *
     * @return list<array{name:string,type:string,nullable:bool,default:?string}>
     */
    abstract public function columnsForTable(\Sofy\Database\Connection $conn, string $table): array;

    // ── To be implemented per driver ─────────────────────────────────────────

    /** Translate a generic Sofy column type to the driver's column type. */
    abstract protected function mapType(string $type, ColumnDefinition $col): string;

    /** Tail modifiers — MySQL's AUTO_INCREMENT / COMMENT / AFTER, empty elsewhere. */
    abstract protected function columnSuffix(ColumnDefinition $col): string;

    /** Format a default literal — handles bool encoding per driver. */
    abstract protected function formatDefault(mixed $value, ColumnDefinition $col): string;
}
