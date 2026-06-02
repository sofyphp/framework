<?php

declare(strict_types=1);

namespace Sofy\Database;

/**
 * Model-aware query builder — wraps QueryBuilder and returns hydrated Model instances.
 *
 * @template T of Model
 */
class Builder
{
    private QueryBuilder $qb;

    /** @var string[] relation names to eager-load */
    private array $with = [];

    /** @param class-string<T> $modelClass */
    public function __construct(private readonly string $modelClass)
    {
        /** @var Model $modelClass */
        $this->qb = Connection::getDefault()->table($modelClass::getTable());
    }

    /** Specify relations to eager-load: User::with('posts', 'profile')->get() */
    public function with(string ...$relations): static
    {
        $this->with = array_merge($this->with, $relations);
        return $this;
    }

    // ── Conditions ────────────────────────────────────────────────────────────

    public function where(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        $value === null
            ? $this->qb->where($column, $operatorOrValue)
            : $this->qb->where($column, $operatorOrValue, $value);
        return $this;
    }

    public function orWhere(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        $value === null
            ? $this->qb->orWhere($column, $operatorOrValue)
            : $this->qb->orWhere($column, $operatorOrValue, $value);
        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        $this->qb->whereIn($column, $values);
        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->qb->whereNull($column);
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->qb->whereNotNull($column);
        return $this;
    }

    // ── Ordering / Pagination ────────────────────────────────────────────────

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        // SQL-inject guard: direction is interpolated into the query string
        // by QueryBuilder, so anything outside the allow-list is rejected.
        // Catches the classic ?sort=ASC%3B%20DROP%20TABLE%20users pattern
        // when devs forward user input straight into orderBy() — defenders
        // shouldn't need to remember to sanitise this themselves.
        $direction = strtoupper(trim($direction));
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException(
                "Builder::orderBy() direction must be 'ASC' or 'DESC', got '{$direction}'.",
            );
        }
        $this->qb->orderBy($column, $direction);
        return $this;
    }

    public function latest(string $column = 'created_at'): static
    {
        $this->qb->latest($column);
        return $this;
    }

    public function oldest(string $column = 'created_at'): static
    {
        $this->qb->oldest($column);
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->qb->limit($limit);
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->qb->offset($offset);
        return $this;
    }

    public function forPage(int $page, int $perPage = 15): static
    {
        $this->qb->forPage($page, $perPage);
        return $this;
    }

    // ── Reads ────────────────────────────────────────────────────────────────

    /** @return T[] */
    public function get(): array
    {
        $class  = $this->modelClass;
        $models = array_map(fn($row) => $class::fromArray($row, true), $this->qb->get());

        if (!empty($this->with) && !empty($models)) {
            $this->eagerLoadRelations($models);
        }

        return $models;
    }

    /** @return T|null */
    public function first(): ?Model
    {
        $row   = $this->qb->first();
        $class = $this->modelClass;
        if (!$row) {
            return null;
        }
        $model = $class::fromArray($row, true);

        if (!empty($this->with)) {
            $this->eagerLoadRelations([$model]);
        }

        return $model;
    }

    /** @param Model[] $models */
    private function eagerLoadRelations(array $models): void
    {
        $first = $models[0];
        foreach ($this->with as $relation) {
            if (!method_exists($first, $relation)) {
                continue;
            }
            $rel = $first->$relation();
            if ($rel instanceof \Sofy\Database\Relations\Relation || $rel instanceof \Sofy\Database\BelongsToMany) {
                $rel->eagerLoad($models, $relation);
            }
        }
    }

    /** @return T */
    public function firstOrFail(): Model
    {
        return $this->first()
            ?? throw new \RuntimeException("{$this->modelClass} not found.");
    }

    public function find(int|string $id): ?Model
    {
        $class = $this->modelClass;
        $row   = $this->qb->find($id, $class::getPrimaryKeyName());
        return $row ? $class::fromArray($row, true) : null;
    }

    public function count(): int   { return $this->qb->count(); }
    public function exists(): bool { return $this->qb->exists(); }

    // ── Writes ────────────────────────────────────────────────────────────────

    public function update(array $data): int { return $this->qb->update($data); }
    public function delete(): int            { return $this->qb->delete(); }

    public function pluck(string $column): array { return $this->qb->pluck($column); }

    public function toSql(): string { return $this->qb->toSql(); }
}
