<?php

declare(strict_types=1);

namespace Sofy\Database\Schema\Grammars;

use Sofy\Database\Schema\ColumnDefinition;
use Sofy\Database\Schema\Grammar;

/**
 * MySQL / MariaDB grammar — the framework's historical default. Backtick
 * identifiers, ENGINE=InnoDB, AUTO_INCREMENT trailing the column type,
 * SET FOREIGN_KEY_CHECKS toggling, SHOW TABLES catalog queries.
 */
class MySqlGrammar extends Grammar
{
    public function quoteId(string $id): string
    {
        return '`' . str_replace('`', '``', $id) . '`';
    }

    public function tableOptions(): string
    {
        return ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }

    public function disableFKsSql(): ?string  { return 'SET FOREIGN_KEY_CHECKS = 0'; }
    public function enableFKsSql(): ?string   { return 'SET FOREIGN_KEY_CHECKS = 1'; }
    public function listTablesSql(): string   { return 'SHOW TABLES'; }
    public function hasTableSql(): string     { return 'SHOW TABLES LIKE ?'; }
    public function hasColumnSql(string $table): string
    {
        return 'SHOW COLUMNS FROM ' . $this->quoteId($table) . ' LIKE ?';
    }

    public function columnsForTable(\Sofy\Database\Connection $conn, string $table): array
    {
        $rows = $conn->query('SHOW COLUMNS FROM ' . $this->quoteId($table));
        return array_map(static fn(array $r): array => [
            'name'     => (string) ($r['Field']   ?? ''),
            'type'     => (string) ($r['Type']    ?? ''),
            'nullable' => ($r['Null']    ?? 'NO') === 'YES',
            'default'  => isset($r['Default']) ? (string) $r['Default'] : null,
        ], $rows);
    }

    protected function mapType(string $type, ColumnDefinition $col): string
    {
        $t = $type;
        if ($col->isUnsigned() && !str_contains($t, 'UNSIGNED')) {
            $t .= ' UNSIGNED';
        }
        return $t;
    }

    protected function columnSuffix(ColumnDefinition $col): string
    {
        $s = '';
        if ($col->isAutoIncrement()) {
            $s .= ' AUTO_INCREMENT';
        }
        if ($col->getComment() !== null) {
            $s .= " COMMENT '" . addslashes($col->getComment()) . "'";
        }
        if ($col->getAfter() !== null) {
            $s .= ' AFTER ' . $this->quoteId($col->getAfter());
        }
        return $s;
    }

    protected function formatDefault(mixed $value, ColumnDefinition $col): string
    {
        if ($value === null)  return 'NULL';
        if ($value === true)  return '1';
        if ($value === false) return '0';
        if (is_int($value) || is_float($value)) return (string) $value;
        return "'" . addslashes((string) $value) . "'";
    }
}
