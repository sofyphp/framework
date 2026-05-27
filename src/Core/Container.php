<?php

declare(strict_types=1);

namespace Sofy\Core;

use Closure;
use ReflectionClass;
use ReflectionNamedType;

class Container
{
    private array $bindings   = [];
    private array $singletons = [];
    private array $instances  = [];

    /**
     * Resolved dependency descriptors — cached so ReflectionClass runs only
     * once per class per process lifetime (helps with Swoole / RoadRunner;
     * still saves repeated work within a single PHP-FPM request).
     *
     * null  → class has no constructor
     * array → list of dependency descriptors
     *
     * @var array<string, list<array{name:string,type:string|null,hasDefault:bool,default:mixed}>|null>
     */
    private array $depCache = [];

    public function bind(string $abstract, Closure|string $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    public function singleton(string $abstract, Closure|string $factory): void
    {
        $this->singletons[$abstract] = $factory;
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function make(string $abstract, array $params = []): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->singletons[$abstract])) {
            $factory = $this->singletons[$abstract];
            $instance = $factory instanceof Closure
                ? $factory($this, ...$params)
                : $this->build($factory, $params);
            $this->instances[$abstract] = $instance;
            return $instance;
        }

        if (isset($this->bindings[$abstract])) {
            $factory = $this->bindings[$abstract];
            return $factory instanceof Closure
                ? $factory($this, ...$params)
                : $this->build($factory, $params);
        }

        return $this->build($abstract, $params);
    }

    private function build(string $class, array $params = []): mixed
    {
        if (!isset($this->depCache[$class])) {
            $this->depCache[$class] = $this->extractDeps($class);
        }

        $deps = $this->depCache[$class];

        if ($deps === null) {
            return new $class();
        }

        $resolved = [];
        foreach ($deps as $dep) {
            if (array_key_exists($dep['name'], $params)) {
                $resolved[] = $params[$dep['name']];
            } elseif ($dep['type'] !== null) {
                $resolved[] = $this->make($dep['type']);
            } elseif ($dep['hasDefault']) {
                $resolved[] = $dep['default'];
            } else {
                throw new \RuntimeException(
                    "Cannot resolve \${$dep['name']} in $class"
                );
            }
        }

        return new $class(...$resolved);
    }

    /**
     * Extract and cache dependency descriptors for a class constructor.
     * Runs ReflectionClass exactly once per class; subsequent builds use the cache.
     *
     * @return list<array{name:string,type:string|null,hasDefault:bool,default:mixed}>|null
     *         null when the class has no constructor (plain new $class())
     */
    private function extractDeps(string $class): ?array
    {
        $reflector = new ReflectionClass($class);

        if (!$reflector->isInstantiable()) {
            throw new \RuntimeException("[$class] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return null;
        }

        $deps = [];
        foreach ($constructor->getParameters() as $param) {
            $type     = $param->getType();
            $typeName = ($type instanceof ReflectionNamedType && !$type->isBuiltin())
                ? $type->getName()
                : null;

            $deps[] = [
                'name'       => $param->getName(),
                'type'       => $typeName,
                'hasDefault' => $param->isDefaultValueAvailable(),
                'default'    => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
            ];
        }

        return $deps;
    }

    public function has(string $abstract): bool
    {
        return isset($this->instances[$abstract])
            || isset($this->bindings[$abstract])
            || isset($this->singletons[$abstract]);
    }
}
