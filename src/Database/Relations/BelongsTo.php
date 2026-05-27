<?php

declare(strict_types=1);

namespace Sofy\Database\Relations;

class BelongsTo extends Relation
{
    public function __construct(
        private readonly string $related,
        private readonly string $foreignKey,  // column on the owning model (e.g. user_id)
        private readonly string $ownerKey,    // column on the related model (e.g. id)
        private readonly mixed  $foreignValue,
    ) {}

    public function get(): mixed
    {
        if ($this->foreignValue === null) {
            return null;
        }
        return ($this->related)::where($this->ownerKey, $this->foreignValue)->first();
    }

    public function eagerLoad(array $models, string $name): void
    {
        $fkValues = array_values(array_unique(array_filter(
            array_map(fn($m) => $m->getAttribute($this->foreignKey), $models)
        )));

        if (empty($fkValues)) {
            foreach ($models as $model) {
                $model->setRelation($name, null);
            }
            return;
        }

        $results = ($this->related)::query()->whereIn($this->ownerKey, $fkValues)->get();

        $map = [];
        foreach ($results as $result) {
            $map[$result->getAttribute($this->ownerKey)] = $result;
        }

        foreach ($models as $model) {
            $fkVal = $model->getAttribute($this->foreignKey);
            $model->setRelation($name, $map[$fkVal] ?? null);
        }
    }
}
