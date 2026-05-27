<?php

declare(strict_types=1);

namespace Sofy\Database\Relations;

class HasMany extends Relation
{
    public function __construct(
        private readonly string $related,
        private readonly string $foreignKey,
        private readonly string $localKey,
        private readonly mixed  $localValue,
    ) {}

    /** @return \Sofy\Database\Model[] */
    public function get(): array
    {
        return ($this->related)::where($this->foreignKey, $this->localValue)->get();
    }

    public function eagerLoad(array $models, string $name): void
    {
        $ids = array_values(array_unique(array_filter(
            array_map(fn($m) => $m->getAttribute($this->localKey), $models)
        )));

        if (empty($ids)) {
            foreach ($models as $model) {
                $model->setRelation($name, []);
            }
            return;
        }

        $results = ($this->related)::query()->whereIn($this->foreignKey, $ids)->get();

        $map = [];
        foreach ($results as $result) {
            $map[$result->getAttribute($this->foreignKey)][] = $result;
        }

        foreach ($models as $model) {
            $model->setRelation($name, $map[$model->getAttribute($this->localKey)] ?? []);
        }
    }
}
