<?php

declare(strict_types=1);

namespace Sofy\Database\Relations;

class HasManyThrough extends Relation
{
    /**
     * @param string $related    Final model class (e.g. Post)
     * @param string $through    Intermediate model class (e.g. User)
     * @param string $firstKey   FK on $through pointing to parent (e.g. country_id)
     * @param string $secondKey  FK on $related pointing to $through (e.g. user_id)
     * @param string $localKey   PK on parent (e.g. id)
     * @param string $throughKey PK on $through (e.g. id)
     */
    public function __construct(
        private readonly string $related,
        private readonly string $through,
        private readonly string $firstKey,
        private readonly string $secondKey,
        private readonly string $localKey,
        private readonly string $throughKey,
        private readonly mixed  $localValue,
    ) {}

    /** @return \Sofy\Database\Model[] */
    public function get(): array
    {
        $intermediates = ($this->through)::where($this->firstKey, $this->localValue)->get();
        if (empty($intermediates)) {
            return [];
        }

        $throughIds = array_map(fn($m) => $m->getAttribute($this->throughKey), $intermediates);
        return ($this->related)::query()->whereIn($this->secondKey, $throughIds)->get();
    }

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

        $intermediates = ($this->through)::query()->whereIn($this->firstKey, $localValues)->get();

        $localToThrough = [];
        foreach ($intermediates as $inter) {
            $lv = $inter->getAttribute($this->firstKey);
            $localToThrough[$lv][] = $inter->getAttribute($this->throughKey);
        }

        $allThroughIds = array_unique(array_merge(...array_values($localToThrough) ?: [[]]));

        if (empty($allThroughIds)) {
            foreach ($models as $model) {
                $model->setRelation($name, []);
            }
            return;
        }

        $related = ($this->related)::query()->whereIn($this->secondKey, $allThroughIds)->get();

        $throughToRelated = [];
        foreach ($related as $r) {
            $throughToRelated[$r->getAttribute($this->secondKey)][] = $r;
        }

        foreach ($models as $model) {
            $lv      = $model->getAttribute($this->localKey);
            $through = $localToThrough[$lv] ?? [];
            $results = [];
            foreach ($through as $tid) {
                $results = array_merge($results, $throughToRelated[$tid] ?? []);
            }
            $model->setRelation($name, $results);
        }
    }
}
