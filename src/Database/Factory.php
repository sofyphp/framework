<?php

declare(strict_types=1);

namespace Sofy\Database;

/**
 * Base model factory for tests and seeders.
 *
 * Usage:
 *   UserFactory::new()->make();
 *   UserFactory::new()->create(['name' => 'Alice']);
 *   UserFactory::new()->count(5)->create();
 *   UserFactory::new()->state(['role' => 'admin'])->create();
 */
abstract class Factory
{
    /** @var class-string<Model> */
    protected string $model = '';

    private int   $count  = 1;
    private array $states = [];

    abstract public function definition(): array;

    public static function new(): static
    {
        return new static();
    }

    public function count(int $n): static
    {
        $clone        = clone $this;
        $clone->count = $n;
        return $clone;
    }

    public function state(array $attributes): static
    {
        $clone           = clone $this;
        $clone->states[] = $attributes;
        return $clone;
    }

    /** Make model instance(s) without persisting. */
    public function make(array $override = []): Model|array
    {
        if ($this->count === 1) {
            return $this->makeOne($override);
        }
        return array_map(fn() => $this->makeOne($override), range(1, $this->count));
    }

    /** Persist model instance(s) to the database. */
    public function create(array $override = []): Model|array
    {
        if ($this->count === 1) {
            $model = $this->makeOne($override);
            $model->save();
            return $model;
        }
        return array_map(function () use ($override) {
            $model = $this->makeOne($override);
            $model->save();
            return $model;
        }, range(1, $this->count));
    }

    private function makeOne(array $override): Model
    {
        $attrs = $this->definition();
        foreach ($this->states as $state) {
            $attrs = array_merge($attrs, $state);
        }
        $attrs = array_merge($attrs, $override);

        $class = $this->model;
        return new $class($attrs);
    }
}
