<?php

declare(strict_types=1);

namespace Sofy\Database\Relations;

abstract class Relation
{
    abstract public function get(): mixed;

    /**
     * Bulk-load this relation for a set of parent models.
     * Sets results on each model via $model->setRelation($name, ...).
     *
     * @param \Sofy\Database\Model[] $models
     */
    abstract public function eagerLoad(array $models, string $name): void;
}
