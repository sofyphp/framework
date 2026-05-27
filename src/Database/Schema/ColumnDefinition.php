<?php

declare(strict_types=1);

namespace Sofy\Database\Schema;

class ColumnDefinition
{
    private bool   $nullable        = false;
    private mixed  $defaultValue    = null;
    private bool   $hasDefault      = false;
    private bool   $isUnique        = false;
    private bool   $isIndex         = false;
    private bool   $isAutoIncrement = false;
    private bool   $isUnsigned      = false;
    private ?string $after          = null;
    private ?string $comment        = null;

    public function __construct(
        public readonly string $name,
        public readonly string $type,
    ) {}

    public function nullable(bool $value = true): static
    {
        $this->nullable = $value;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->defaultValue = $value;
        $this->hasDefault   = true;
        return $this;
    }

    public function unique(): static
    {
        $this->isUnique = true;
        return $this;
    }

    public function index(): static
    {
        $this->isIndex = true;
        return $this;
    }

    public function unsigned(): static
    {
        $this->isUnsigned = true;
        return $this;
    }

    public function autoIncrement(): static
    {
        $this->isAutoIncrement = true;
        return $this;
    }

    public function after(string $column): static
    {
        $this->after = $column;
        return $this;
    }

    public function comment(string $text): static
    {
        $this->comment = $text;
        return $this;
    }

    public function shouldAddUniqueKey(): bool { return $this->isUnique; }
    public function shouldAddIndex(): bool     { return $this->isIndex && !$this->isUnique; }

    /**
     * Возвращает SQL только для определения колонки (без KEY — они идут отдельно).
     */
    public function toSql(): string
    {
        $type = $this->type;
        if ($this->isUnsigned && !str_contains($type, 'UNSIGNED')) {
            $type .= ' UNSIGNED';
        }

        $sql = "`{$this->name}` {$type}";
        $sql .= $this->nullable ? ' NULL' : ' NOT NULL';

        if ($this->hasDefault) {
            $sql .= ' DEFAULT ' . $this->formatDefault($this->defaultValue);
        }

        if ($this->isAutoIncrement) {
            $sql .= ' AUTO_INCREMENT';
        }

        if ($this->comment !== null) {
            $sql .= " COMMENT '" . addslashes($this->comment) . "'";
        }

        if ($this->after !== null) {
            $sql .= " AFTER `{$this->after}`";
        }

        return $sql;
    }

    private function formatDefault(mixed $value): string
    {
        if ($value === null)  return 'NULL';
        if ($value === true)  return '1';
        if ($value === false) return '0';
        if (is_int($value) || is_float($value)) return (string) $value;
        return "'" . addslashes((string) $value) . "'";
    }
}
