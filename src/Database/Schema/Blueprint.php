<?php

declare(strict_types=1);

namespace Sofy\Database\Schema;

class Blueprint
{
    /** @var ColumnDefinition[] */
    private array $columns     = [];
    private array $indexes     = [];
    private array $foreignKeys = [];
    private ?string $primaryKeyColumn = null;

    // ── Column types ──────────────────────────────────────────────────────────

    public function id(string $name = 'id'): ColumnDefinition
    {
        $this->primaryKeyColumn = $name;
        return $this->addColumn($name, 'BIGINT UNSIGNED')->autoIncrement()->unsigned();
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return $this->addColumn($name, "VARCHAR($length)");
    }

    public function char(string $name, int $length = 100): ColumnDefinition
    {
        return $this->addColumn($name, "CHAR($length)");
    }

    public function text(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'TEXT');
    }

    public function longText(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'LONGTEXT');
    }

    public function integer(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'INT');
    }

    public function bigInteger(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'BIGINT');
    }

    public function unsignedBigInteger(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'BIGINT UNSIGNED');
    }

    public function tinyInteger(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'TINYINT');
    }

    public function smallInteger(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'SMALLINT');
    }

    public function float(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'FLOAT');
    }

    public function decimal(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn($name, "DECIMAL($precision,$scale)");
    }

    public function boolean(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'TINYINT(1)');
    }

    public function datetime(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'DATETIME');
    }

    public function timestamp(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'TIMESTAMP');
    }

    public function date(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'DATE');
    }

    public function time(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'TIME');
    }

    public function json(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'JSON');
    }

    public function uuid(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'CHAR(36)');
    }

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

    public function toCreateSql(string $table): string
    {
        $lines = [];

        foreach ($this->columns as $col) {
            $lines[] = '  ' . $col->toSql();
        }

        if ($this->primaryKeyColumn !== null) {
            $lines[] = "  PRIMARY KEY (`{$this->primaryKeyColumn}`)";
        }

        // Inline unique/index from column modifiers
        foreach ($this->columns as $col) {
            if ($col->shouldAddUniqueKey()) {
                $lines[] = "  UNIQUE KEY `uniq_{$col->name}` (`{$col->name}`)";
            } elseif ($col->shouldAddIndex()) {
                $lines[] = "  KEY `idx_{$col->name}` (`{$col->name}`)";
            }
        }

        // Explicit indexes
        foreach ($this->indexes as $idx) {
            $cols = implode(', ', array_map(fn($c) => "`$c`", $idx['columns']));
            $lines[] = $idx['type'] === 'PRIMARY KEY'
                ? "  PRIMARY KEY ($cols)"
                : "  {$idx['type']} `{$idx['name']}` ($cols)";
        }

        foreach ($this->foreignKeys as $fk) {
            $lines[] = '  ' . $fk->toSql();
        }

        return "CREATE TABLE `$table` (\n"
             . implode(",\n", $lines)
             . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    public function toAlterAddSql(string $table): string
    {
        $parts = [];

        foreach ($this->columns as $col) {
            $parts[] = 'ADD COLUMN ' . $col->toSql();
        }
        foreach ($this->indexes as $idx) {
            $cols    = implode(', ', array_map(fn($c) => "`$c`", $idx['columns']));
            $parts[] = "ADD {$idx['type']} `{$idx['name']}` ($cols)";
        }
        foreach ($this->foreignKeys as $fk) {
            $parts[] = 'ADD ' . $fk->toSql();
        }

        return "ALTER TABLE `$table` " . implode(', ', $parts);
    }

    public function toAlterDropSql(string $table, array $columns): string
    {
        $parts = array_map(fn($c) => "DROP COLUMN `$c`", $columns);
        return "ALTER TABLE `$table` " . implode(', ', $parts);
    }

    private function addColumn(string $name, string $type): ColumnDefinition
    {
        $col = new ColumnDefinition($name, $type);
        $this->columns[] = $col;
        return $col;
    }
}
