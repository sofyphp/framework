<?php

declare(strict_types=1);

namespace Sofy\Database\Schema;

use Sofy\Database\Schema\Grammars\MySqlGrammar;

/**
 * Pure data holder for a column definition. SQL generation lives on
 * Grammar (one per driver) — ColumnDefinition just collects the facts
 * the migration author declared.
 *
 * Back-compat: toSql() with no Grammar still emits MySQL SQL, matching
 * pre-0.2.0 behaviour for any caller that hasn't been updated yet.
 */
class ColumnDefinition
{
    private bool   $nullable        = false;
    private mixed  $defaultValue    = null;
    private bool   $hasDefault      = false;
    private bool   $isUnique        = false;
    private bool   $isIndex         = false;
    private bool   $isAutoIncrement = false;
    private bool   $isUnsigned      = false;
    private bool   $isPrimaryKey    = false;
    private ?string $after          = null;
    private ?string $comment        = null;

    public function __construct(
        public readonly string $name,
        public readonly string $type,
    ) {}

    // ── Builder API ───────────────────────────────────────────────────────────

    public function nullable(bool $value = true): static    { $this->nullable = $value;       return $this; }
    public function default(mixed $value): static           { $this->defaultValue = $value; $this->hasDefault = true; return $this; }
    public function unique(): static                        { $this->isUnique = true;         return $this; }
    public function index(): static                         { $this->isIndex = true;          return $this; }
    public function unsigned(): static                      { $this->isUnsigned = true;       return $this; }
    public function autoIncrement(): static                 { $this->isAutoIncrement = true;  return $this; }
    public function primary(): static                       { $this->isPrimaryKey = true;     return $this; }
    public function after(string $column): static           { $this->after = $column;         return $this; }
    public function comment(string $text): static           { $this->comment = $text;         return $this; }

    // ── Getters (used by Grammar / Blueprint) ─────────────────────────────────

    public function isNullable(): bool       { return $this->nullable; }
    public function isUnique(): bool         { return $this->isUnique; }
    public function isIndex(): bool          { return $this->isIndex; }
    public function isAutoIncrement(): bool  { return $this->isAutoIncrement; }
    public function isUnsigned(): bool       { return $this->isUnsigned; }
    public function isPrimaryKey(): bool     { return $this->isPrimaryKey || $this->isAutoIncrement; }
    public function hasDefault(): bool       { return $this->hasDefault; }
    public function getDefault(): mixed      { return $this->defaultValue; }
    public function getAfter(): ?string      { return $this->after; }
    public function getComment(): ?string    { return $this->comment; }

    public function shouldAddUniqueKey(): bool { return $this->isUnique; }
    public function shouldAddIndex(): bool     { return $this->isIndex && !$this->isUnique; }

    /**
     * Back-compat shim: emit MySQL column SQL directly. New code should call
     * $grammar->columnSql($column) instead.
     */
    public function toSql(?Grammar $grammar = null): string
    {
        return ($grammar ?? new MySqlGrammar())->columnSql($this);
    }
}
