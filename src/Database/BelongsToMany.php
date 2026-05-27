<?php

declare(strict_types=1);

namespace Sofy\Database;

/**
 * Many-to-many relationship via a pivot table.
 *
 * Typical usage in a model:
 *
 *   public function roles(): BelongsToMany
 *   {
 *       return $this->belongsToMany(Role::class);
 *   }
 *
 *   // Retrieve
 *   $user->roles()->get();
 *
 *   // Mutate
 *   $user->roles()->attach([1, 2, 3]);
 *   $user->roles()->detach(2);
 *   $user->roles()->sync([1, 3]);
 */
class BelongsToMany
{
    /**
     * @param class-string<Model> $relatedClass
     * @param string              $pivotTable    e.g. 'role_user'
     * @param string              $foreignKey    FK for the owning model in the pivot, e.g. 'user_id'
     * @param string              $relatedKey    FK for the related model in the pivot, e.g. 'role_id'
     * @param mixed               $localValue    PK value of the owning instance
     */
    public function __construct(
        private readonly string $relatedClass,
        private readonly string $pivotTable,
        private readonly string $foreignKey,
        private readonly string $relatedKey,
        private readonly mixed  $localValue,
        private readonly string $localKey = 'id',
    ) {}

    // ── Reads ─────────────────────────────────────────────────────────────────

    /** @return Model[] */
    public function get(): array
    {
        $pivotRows = Connection::getDefault()
            ->table($this->pivotTable)
            ->where($this->foreignKey, $this->localValue)
            ->get();

        if (empty($pivotRows)) {
            return [];
        }

        $relatedIds = array_column($pivotRows, $this->relatedKey);

        /** @var class-string<Model> $cls */
        $cls = $this->relatedClass;
        return $cls::query()->whereIn($cls::getPrimaryKeyName(), $relatedIds)->get();
    }

    /** Return a query Builder scoped to the related models. */
    public function query(): Builder
    {
        $pivotRows  = Connection::getDefault()
            ->table($this->pivotTable)
            ->where($this->foreignKey, $this->localValue)
            ->get();
        $relatedIds = array_column($pivotRows, $this->relatedKey);

        /** @var class-string<Model> $cls */
        $cls = $this->relatedClass;
        return $cls::query()->whereIn($cls::getPrimaryKeyName(), $relatedIds ?: [0]);
    }

    public function count(): int
    {
        return Connection::getDefault()
            ->table($this->pivotTable)
            ->where($this->foreignKey, $this->localValue)
            ->count();
    }

    /** Pluck a column from the pivot table. */
    public function pluck(string $column): array
    {
        return array_column(
            Connection::getDefault()
                ->table($this->pivotTable)
                ->where($this->foreignKey, $this->localValue)
                ->get(),
            $column
        );
    }

    // ── Eager loading ─────────────────────────────────────────────────────────

    /** @param Model[] $models */
    public function eagerLoad(array $models, string $name): void
    {
        $localValues = array_values(array_unique(array_filter(
            array_map(fn($m) => $m->getAttribute($this->localKey), $models)
        )));

        if (empty($localValues)) {
            foreach ($models as $model) {
                $model->setRelation($name, []);
            }
            return;
        }

        $pivotRows = Connection::getDefault()
            ->table($this->pivotTable)
            ->whereIn($this->foreignKey, $localValues)
            ->get();

        if (empty($pivotRows)) {
            foreach ($models as $model) {
                $model->setRelation($name, []);
            }
            return;
        }

        $relatedIds = array_unique(array_column($pivotRows, $this->relatedKey));

        /** @var class-string<Model> $cls */
        $cls     = $this->relatedClass;
        $related = $cls::query()->whereIn($cls::getPrimaryKeyName(), $relatedIds)->get();

        $relatedMap = [];
        foreach ($related as $r) {
            $relatedMap[$r->getAttribute($cls::getPrimaryKeyName())] = $r;
        }

        // Build: localValue → [related, ...]
        $map = [];
        foreach ($pivotRows as $row) {
            $lv = $row[$this->foreignKey];
            $rv = $row[$this->relatedKey];
            if (isset($relatedMap[$rv])) {
                $map[$lv][] = $relatedMap[$rv];
            }
        }

        foreach ($models as $model) {
            $lv = $model->getAttribute($this->localKey);
            $model->setRelation($name, $map[$lv] ?? []);
        }
    }

    // ── Writes ────────────────────────────────────────────────────────────────

    /**
     * Attach one or more related IDs.
     *
     * @param int|string|array<int|string> $ids
     * @param array<string, mixed>         $pivotData  Extra columns for the pivot row
     */
    public function attach(int|string|array $ids, array $pivotData = []): void
    {
        $ids = (array) $ids;
        $qb  = Connection::getDefault()->table($this->pivotTable);

        foreach ($ids as $id) {
            $qb->insert(array_merge([
                $this->foreignKey => $this->localValue,
                $this->relatedKey => $id,
            ], $pivotData));
        }
    }

    /**
     * Detach one or more related IDs. Omit $ids to detach all.
     *
     * @param int|string|array<int|string> $ids
     */
    public function detach(int|string|array $ids = []): int
    {
        $qb = Connection::getDefault()
            ->table($this->pivotTable)
            ->where($this->foreignKey, $this->localValue);

        if (!empty($ids)) {
            $qb->whereIn($this->relatedKey, (array) $ids);
        }

        return $qb->delete();
    }

    /**
     * Sync: attach missing IDs, detach removed IDs, keep existing.
     *
     * @param array<int|string> $ids
     */
    public function sync(array $ids): array
    {
        $pivotRows = Connection::getDefault()
            ->table($this->pivotTable)
            ->where($this->foreignKey, $this->localValue)
            ->get();

        $existing = array_column($pivotRows, $this->relatedKey);
        $toAttach = array_values(array_diff($ids, $existing));
        $toDetach = array_values(array_diff($existing, $ids));

        if (!empty($toAttach)) {
            $this->attach($toAttach);
        }
        if (!empty($toDetach)) {
            $this->detach($toDetach);
        }

        return ['attached' => $toAttach, 'detached' => $toDetach];
    }
}
