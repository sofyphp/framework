<?php

declare(strict_types=1);

namespace Sofy\Database\Schema;

class ForeignKeyDefinition
{
    private string $referencedColumn = 'id';
    private string $referencedTable  = '';
    private string $onDelete         = 'RESTRICT';
    private string $onUpdate         = 'RESTRICT';

    public function __construct(private readonly string $column) {}

    public function references(string $column): static
    {
        $this->referencedColumn = $column;
        return $this;
    }

    public function on(string $table): static
    {
        $this->referencedTable = $table;
        return $this;
    }

    public function onDelete(string $action): static
    {
        $this->onDelete = strtoupper($action);
        return $this;
    }

    public function onUpdate(string $action): static
    {
        $this->onUpdate = strtoupper($action);
        return $this;
    }

    public function cascadeOnDelete(): static { return $this->onDelete('CASCADE'); }
    public function nullOnDelete(): static    { return $this->onDelete('SET NULL'); }
    public function cascadeOnUpdate(): static { return $this->onUpdate('CASCADE'); }

    public function toSql(): string
    {
        $name = "fk_{$this->column}_{$this->referencedTable}";
        return "CONSTRAINT `{$name}` FOREIGN KEY (`{$this->column}`) "
             . "REFERENCES `{$this->referencedTable}` (`{$this->referencedColumn}`) "
             . "ON DELETE {$this->onDelete} ON UPDATE {$this->onUpdate}";
    }
}
