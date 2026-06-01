<?php

declare(strict_types=1);

namespace Sofy\Database\Schema;

use Sofy\Database\Schema\Grammars\MySqlGrammar;

class Blueprint
{
    /** @var ColumnDefinition[] */
    private array $columns     = [];
    private array $indexes     = [];
    /** @var ForeignKeyDefinition[] */
    private array $foreignKeys = [];
    private ?string $primaryKeyColumn = null;

    // ── Column types ──────────────────────────────────────────────────────────

    public function id(string $name = 'id'): ColumnDefinition
    {
        $this->primaryKeyColumn = $name;
        return $this->addColumn($name, 'BIGINT UNSIGNED')->autoIncrement()->unsigned();
    }

    public function string(string $name, int $length = 255): ColumnDefinition { return $this->addColumn($name, "VARCHAR($length)"); }
    public function char(string $name, int $length = 100): ColumnDefinition  { return $this->addColumn($name, "CHAR($length)"); }
    public function text(string $name): ColumnDefinition                     { return $this->addColumn($name, 'TEXT'); }
    public function longText(string $name): ColumnDefinition                 { return $this->addColumn($name, 'LONGTEXT'); }
    public function integer(string $name): ColumnDefinition                  { return $this->addColumn($name, 'INT'); }
    public function bigInteger(string $name): ColumnDefinition               { return $this->addColumn($name, 'BIGINT'); }
    public function unsignedBigInteger(string $name): ColumnDefinition       { return $this->addColumn($name, 'BIGINT UNSIGNED'); }
    public function tinyInteger(string $name): ColumnDefinition              { return $this->addColumn($name, 'TINYINT'); }
    public function smallInteger(string $name): ColumnDefinition             { return $this->addColumn($name, 'SMALLINT'); }
    public function float(string $name): ColumnDefinition                    { return $this->addColumn($name, 'FLOAT'); }
    public function decimal(string $name, int $precision = 8, int $scale = 2): ColumnDefinition { return $this->addColumn($name, "DECIMAL($precision,$scale)"); }
    public function boolean(string $name): ColumnDefinition                  { return $this->addColumn($name, 'TINYINT(1)'); }
    public function datetime(string $name): ColumnDefinition                 { return $this->addColumn($name, 'DATETIME'); }
    public function timestamp(string $name): ColumnDefinition                { return $this->addColumn($name, 'TIMESTAMP'); }
    public function date(string $name): ColumnDefinition                     { return $this->addColumn($name, 'DATE'); }
    public function time(string $name): ColumnDefinition                     { return $this->addColumn($name, 'TIME'); }
    public function json(string $name): ColumnDefinition                     { return $this->addColumn($name, 'JSON'); }
    public function uuid(string $name): ColumnDefinition                     { return $this->addColumn($name, 'CHAR(36)'); }

    public function enum(string $name, array $values): ColumnDefinition
    {
        $vals = implode(', ', array_map(fn($v) => "'" . addslashes($v) . "'", $values));
        return $this->addColumn($name, "ENUM($vals)");
    }

    // ── Shorthand groups ──────────────────────────────────────────────────────

    public function timestamps(): void
    {
        $this->addColumn('created_at', 'DATETIME')->nullable();
        $this->addColumn('updated_at', 'DATETIME')->nullable();
    }

    public function softDeletes(string $column = 'deleted_at'): void
    {
        $this->addColumn($column, 'DATETIME')->nullable();
    }

    public function rememberToken(): void
    {
        $this->addColumn('remember_token', 'VARCHAR(100)')->nullable();
    }

    // ── Indexes ───────────────────────────────────────────────────────────────

    public function primary(array $columns): void
    {
        $this->indexes[] = ['type' => 'PRIMARY KEY', 'name' => '', 'columns' => $columns];
    }

    public function index(array|string $columns, ?string $name = null): void
    {
        $cols      = (array) $columns;
        $indexName = $name ?? 'idx_' . implode('_', $cols);
        $this->indexes[] = ['type' => 'KEY', 'name' => $indexName, 'columns' => $cols];
    }

    public function unique(array|string $columns, ?string $name = null): void
    {
        $cols      = (array) $columns;
        $indexName = $name ?? 'uniq_' . implode('_', $cols);
        $this->indexes[] = ['type' => 'UNIQUE KEY', 'name' => $indexName, 'columns' => $cols];
    }

    public function foreign(string $column): ForeignKeyDefinition
    {
        $fk = new ForeignKeyDefinition($column);
        $this->foreignKeys[] = $fk;
        return $fk;
    }

    // ── SQL generation ────────────────────────────────────────────────────────

    public function toCreateSql(string $table, ?Grammar $grammar = null): string
    {
        $g       = $grammar ?? new MySqlGrammar();
        $lines   = [];
        $inlined = [];

        // 1) Column definitions. Note which columns Grammar inlined PRIMARY KEY
        //    on so we don't emit a duplicate top-level PRIMARY KEY clause.
        foreach ($this->columns as $col) {
            $lines[] = '  ' . $g->columnSql($col);
            if ($g->pkInlinedInColumn($col)) {
                $inlined[$col->name] = true;
            }
        }

        // 2) The id() helper sets primaryKeyColumn. Emit a separate PRIMARY KEY
        //    only when Grammar didn't already pin it on the column itself
        //    (MySQL needs the line; pgsql/sqlite get PK inline with the column).
        if ($this->primaryKeyColumn !== null && empty($inlined[$this->primaryKeyColumn])) {
            $lines[] = '  PRIMARY KEY (' . $g->quoteId($this->primaryKeyColumn) . ')';
        }

        // 3) Per-column UNIQUE / INDEX modifiers — emit as separate index lines
        //    where possible. For drivers that don't accept inline UNIQUE KEY
        //    inside CREATE TABLE (pgsql, sqlite), we render them as table
        //    constraints — same effect.
        foreach ($this->columns as $col) {
            if ($col->shouldAddUniqueKey()) {
                $lines[] = $this->buildIndexLine($g, 'UNIQUE', "uniq_{$col->name}", [$col->name]);
            } elseif ($col->shouldAddIndex() && $g instanceof MySqlGrammar) {
                // KEY / INDEX inside CREATE TABLE is MySQL-only; for the
                // others we emit a CREATE INDEX after the table is built
                // (see Schema::create()). Skip here.
                $lines[] = "  KEY " . $g->quoteId("idx_{$col->name}") . ' (' . $g->quoteId($col->name) . ')';
            }
        }

        // 4) Explicit indexes (->primary([...]), ->unique([...]), ->index([...])).
        foreach ($this->indexes as $idx) {
            if ($idx['type'] === 'PRIMARY KEY') {
                $cols = implode(', ', array_map($g->quoteId(...), $idx['columns']));
                $lines[] = "  PRIMARY KEY ($cols)";
            } elseif ($idx['type'] === 'UNIQUE KEY') {
                $lines[] = $this->buildIndexLine($g, 'UNIQUE', $idx['name'], $idx['columns']);
            } elseif ($g instanceof MySqlGrammar) {
                // KEY ... inside CREATE TABLE is MySQL-only.
                $cols = implode(', ', array_map($g->quoteId(...), $idx['columns']));
                $lines[] = '  KEY ' . $g->quoteId($idx['name']) . " ($cols)";
            }
        }

        // 5) Foreign keys — same syntax across all three drivers.
        foreach ($this->foreignKeys as $fk) {
            $lines[] = '  ' . $fk->toSql($g);
        }

        return 'CREATE TABLE ' . $g->quoteId($table) . " (\n"
             . implode(",\n", $lines)
             . "\n)" . $g->tableOptions();
    }

    /**
     * Indexes that need to be emitted *after* CREATE TABLE because the driver
     * doesn't accept them inline in a CREATE TABLE column list. Schema::create()
     * runs these as separate statements right after the CREATE.
     *
     * @return list<string>
     */
    public function extraStatements(string $table, ?Grammar $grammar = null): array
    {
        $g = $grammar ?? new MySqlGrammar();
        if ($g instanceof MySqlGrammar) {
            return []; // KEY / INDEX go inline above.
        }

        $out = [];
        foreach ($this->columns as $col) {
            if ($col->shouldAddIndex() && !$col->shouldAddUniqueKey()) {
                $out[] = 'CREATE INDEX ' . $g->quoteId("idx_{$col->name}")
                       . ' ON ' . $g->quoteId($table)
                       . ' (' . $g->quoteId($col->name) . ')';
            }
        }
        foreach ($this->indexes as $idx) {
            if ($idx['type'] === 'KEY') {
                $cols = implode(', ', array_map($g->quoteId(...), $idx['columns']));
                $out[] = 'CREATE INDEX ' . $g->quoteId($idx['name'])
                       . ' ON ' . $g->quoteId($table) . " ($cols)";
            }
        }
        return $out;
    }

    public function toAlterAddSql(string $table, ?Grammar $grammar = null): string
    {
        $g     = $grammar ?? new MySqlGrammar();
        $parts = [];

        foreach ($this->columns as $col) {
            $parts[] = 'ADD COLUMN ' . $g->columnSql($col);
        }
        foreach ($this->indexes as $idx) {
            $cols = implode(', ', array_map($g->quoteId(...), $idx['columns']));
            if ($g instanceof MySqlGrammar) {
                $parts[] = "ADD {$idx['type']} " . $g->quoteId($idx['name']) . " ($cols)";
            } elseif ($idx['type'] === 'UNIQUE KEY') {
                $parts[] = 'ADD CONSTRAINT ' . $g->quoteId($idx['name']) . " UNIQUE ($cols)";
            }
        }
        foreach ($this->foreignKeys as $fk) {
            $parts[] = 'ADD ' . $fk->toSql($g);
        }

        return 'ALTER TABLE ' . $g->quoteId($table) . ' ' . implode(', ', $parts);
    }

    public function toAlterDropSql(string $table, array $columns, ?Grammar $grammar = null): string
    {
        $g     = $grammar ?? new MySqlGrammar();
        $parts = array_map(fn($c) => 'DROP COLUMN ' . $g->quoteId($c), $columns);
        return 'ALTER TABLE ' . $g->quoteId($table) . ' ' . implode(', ', $parts);
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function addColumn(string $name, string $type): ColumnDefinition
    {
        $col = new ColumnDefinition($name, $type);
        $this->columns[] = $col;
        return $col;
    }

    private function buildIndexLine(Grammar $g, string $type, string $name, array $cols): string
    {
        $colList = implode(', ', array_map($g->quoteId(...), $cols));
        // 'UNIQUE KEY' (MySQL) vs 'UNIQUE' table-constraint (pgsql / sqlite).
        if ($g instanceof MySqlGrammar) {
            return "  UNIQUE KEY " . $g->quoteId($name) . " ($colList)";
        }
        return '  CONSTRAINT ' . $g->quoteId($name) . " UNIQUE ($colList)";
    }
}
