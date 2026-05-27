<?php

declare(strict_types=1);

namespace Sofy\Database;

class QueryBuilder
{
    private string $table   = '';
    private array  $columns = ['*'];

    /** @var array<array{type:string, sql:string, bindings:array}> */
    private array $wheres  = [];
    private array $havings = [];

    private array  $joins     = [];
    private array  $groups    = [];
    private array  $orders    = [];
    private ?int   $limitVal  = null;
    private ?int   $offsetVal = null;
    private array  $rawSelect = [];

    public function __construct(private readonly Connection $connection) {}

    public function table(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    public function select(string ...$columns): static
    {
        $this->columns = $columns ?: ['*'];
        return $this;
    }

    public function selectRaw(string $sql, array $bindings = []): static
    {
        $this->columns   = [$sql];
        $this->rawSelect = $bindings;
        return $this;
    }

    // ── Where ────────────────────────────────────────────────────────────────

    public function where(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        [$op, $val] = $this->parseCondition($operatorOrValue, $value);
        $this->wheres[] = ['type' => 'AND', 'sql' => "$column $op ?", 'bindings' => [$val]];
        return $this;
    }

    public function orWhere(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        [$op, $val] = $this->parseCondition($operatorOrValue, $value);
        $this->wheres[] = ['type' => 'OR', 'sql' => "$column $op ?", 'bindings' => [$val]];
        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        $placeholders   = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = ['type' => 'AND', 'sql' => "$column IN ($placeholders)", 'bindings' => $values];
        return $this;
    }

    public function whereNotIn(string $column, array $values): static
    {
        $placeholders   = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = ['type' => 'AND', 'sql' => "$column NOT IN ($placeholders)", 'bindings' => $values];
        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->wheres[] = ['type' => 'AND', 'sql' => "$column IS NULL", 'bindings' => []];
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = ['type' => 'AND', 'sql' => "$column IS NOT NULL", 'bindings' => []];
        return $this;
    }

    public function whereBetween(string $column, mixed $min, mixed $max): static
    {
        $this->wheres[] = ['type' => 'AND', 'sql' => "$column BETWEEN ? AND ?", 'bindings' => [$min, $max]];
        return $this;
    }

    public function whereNotBetween(string $column, mixed $min, mixed $max): static
    {
        $this->wheres[] = ['type' => 'AND', 'sql' => "$column NOT BETWEEN ? AND ?", 'bindings' => [$min, $max]];
        return $this;
    }

    public function whereRaw(string $sql, array $bindings = []): static
    {
        $this->wheres[] = ['type' => 'AND', 'sql' => $sql, 'bindings' => $bindings];
        return $this;
    }

    public function orWhereRaw(string $sql, array $bindings = []): static
    {
        $this->wheres[] = ['type' => 'OR', 'sql' => $sql, 'bindings' => $bindings];
        return $this;
    }

    public function whereDate(string $column, string $value): static
    {
        return $this->whereRaw("DATE($column) = ?", [$value]);
    }

    public function whereYear(string $column, int $year): static
    {
        return $this->whereRaw("YEAR($column) = ?", [$year]);
    }

    public function whereMonth(string $column, int $month): static
    {
        return $this->whereRaw("MONTH($column) = ?", [$month]);
    }

    public function whereDay(string $column, int $day): static
    {
        return $this->whereRaw("DAY($column) = ?", [$day]);
    }

    // ── Joins ────────────────────────────────────────────────────────────────

    public function join(string $table, string $first, string $op, string $second, string $type = 'INNER'): static
    {
        $this->joins[] = "$type JOIN $table ON $first $op $second";
        return $this;
    }

    public function leftJoin(string $table, string $first, string $op, string $second): static
    {
        return $this->join($table, $first, $op, $second, 'LEFT');
    }

    // ── Grouping / Ordering / Pagination ─────────────────────────────────────

    public function groupBy(string ...$columns): static
    {
        $this->groups = array_merge($this->groups, $columns);
        return $this;
    }

    public function having(string $column, string $op, mixed $value): static
    {
        $this->havings[] = ['type' => 'AND', 'sql' => "$column $op ?", 'bindings' => [$value]];
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orders[] = "$column " . strtoupper($direction);
        return $this;
    }

    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'DESC');
    }

    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'ASC');
    }

    public function limit(int $limit): static
    {
        $this->limitVal = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offsetVal = $offset;
        return $this;
    }

    public function forPage(int $page, int $perPage = 15): static
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    // ── Reads ────────────────────────────────────────────────────────────────

    public function get(): array
    {
        return $this->connection->query($this->buildSelectSql(), $this->selectBindings());
    }

    public function first(): ?array
    {
        $results = $this->limit(1)->get();
        return $results[0] ?? null;
    }

    public function find(int|string $id, string $primaryKey = 'id'): ?array
    {
        return $this->where($primaryKey, $id)->first();
    }

    public function count(): int
    {
        $sql    = "SELECT COUNT(*) AS `cnt` FROM `{$this->table}`" . $this->buildWhereSql();
        $result = $this->connection->query($sql, $this->whereBindings());
        return (int) ($result[0]['cnt'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function pluck(string $column): array
    {
        return array_column($this->select($column)->get(), $column);
    }

    // ── Writes ───────────────────────────────────────────────────────────────

    public function insert(array $data): string|false
    {
        $cols         = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $this->connection->execute(
            "INSERT INTO `{$this->table}` ($cols) VALUES ($placeholders)",
            array_values($data)
        );
        return $this->connection->lastInsertId();
    }

    public function insertMany(array $rows): void
    {
        if (empty($rows)) {
            return;
        }
        $cols           = implode(', ', array_map(fn($c) => "`$c`", array_keys($rows[0])));
        $rowPlaceholder = '(' . implode(', ', array_fill(0, count($rows[0]), '?')) . ')';
        $allPlaceholders = implode(', ', array_fill(0, count($rows), $rowPlaceholder));
        $bindings       = array_merge(...array_map('array_values', $rows));
        $this->connection->execute(
            "INSERT INTO `{$this->table}` ($cols) VALUES $allPlaceholders",
            $bindings
        );
    }

    public function update(array $data): int
    {
        $sets     = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        $bindings = array_merge(array_values($data), $this->whereBindings());
        return $this->connection->execute(
            "UPDATE `{$this->table}` SET $sets" . $this->buildWhereSql(),
            $bindings
        );
    }

    public function delete(): int
    {
        return $this->connection->execute(
            "DELETE FROM `{$this->table}`" . $this->buildWhereSql(),
            $this->whereBindings()
        );
    }

    // ── SQL building ─────────────────────────────────────────────────────────

    private function buildSelectSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns);
        $sql .= " FROM `{$this->table}`";

        foreach ($this->joins as $join) {
            $sql .= " $join";
        }

        $sql .= $this->buildWhereSql();

        if ($this->groups) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }

        if ($this->havings) {
            $sql .= ' HAVING ' . $this->buildConditionClause($this->havings);
        }

        if ($this->orders) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->limitVal !== null) {
            $sql .= " LIMIT {$this->limitVal}";
        }

        if ($this->offsetVal !== null) {
            $sql .= " OFFSET {$this->offsetVal}";
        }

        return $sql;
    }

    private function buildWhereSql(): string
    {
        if (empty($this->wheres)) {
            return '';
        }
        return ' WHERE ' . $this->buildConditionClause($this->wheres);
    }

    private function buildConditionClause(array $conditions): string
    {
        $parts = [];
        foreach ($conditions as $i => $cond) {
            $parts[] = ($i > 0 ? $cond['type'] . ' ' : '') . $cond['sql'];
        }
        return implode(' ', $parts);
    }

    private function whereBindings(): array
    {
        $bindings = [];
        foreach ($this->wheres as $where) {
            foreach ($where['bindings'] as $v) {
                $bindings[] = $v;
            }
        }
        return $bindings;
    }

    private function havingBindings(): array
    {
        $bindings = [];
        foreach ($this->havings as $having) {
            foreach ($having['bindings'] as $v) {
                $bindings[] = $v;
            }
        }
        return $bindings;
    }

    private function selectBindings(): array
    {
        return array_merge($this->rawSelect, $this->whereBindings(), $this->havingBindings());
    }

    private function parseCondition(mixed $operatorOrValue, mixed $value): array
    {
        return $value === null ? ['=', $operatorOrValue] : [$operatorOrValue, $value];
    }

    public function paginate(int $perPage = 15, ?int $page = null): Paginator
    {
        $page  = max(1, $page ?? (int) ($_GET['page'] ?? 1));
        $total = $this->count();
        $items = (clone $this)->forPage($page, $perPage)->get();

        return new Paginator($items, $total, $perPage, $page);
    }

    public function toSql(): string
    {
        return $this->buildSelectSql();
    }
}
