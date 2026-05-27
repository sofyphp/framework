<?php

declare(strict_types=1);

namespace Sofy\Support;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

class Collection implements Countable, IteratorAggregate, JsonSerializable
{
    public function __construct(private array $items = []) {}

    public static function make(array $items = []): static
    {
        return new static($items);
    }

    // ── Access ────────────────────────────────────────────────────────────────

    public function all(): array   { return $this->items; }
    public function toArray(): array { return $this->items; }
    public function count(): int   { return count($this->items); }
    public function isEmpty(): bool    { return empty($this->items); }
    public function isNotEmpty(): bool { return !empty($this->items); }

    public function first(callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return !empty($this->items) ? reset($this->items) : $default;
        }
        foreach ($this->items as $k => $v) {
            if ($callback($v, $k)) return $v;
        }
        return $default;
    }

    public function last(callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return !empty($this->items) ? end($this->items) : $default;
        }
        $result = $default;
        foreach ($this->items as $k => $v) {
            if ($callback($v, $k)) $result = $v;
        }
        return $result;
    }

    public function get(int|string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    public function has(int|string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function contains(mixed $value): bool
    {
        if (is_callable($value)) {
            foreach ($this->items as $k => $v) {
                if ($value($v, $k)) return true;
            }
            return false;
        }
        return in_array($value, $this->items, true);
    }

    // ── Transformation ────────────────────────────────────────────────────────

    public function map(callable $callback): static
    {
        return new static(array_map($callback, $this->items));
    }

    public function filter(callable $callback = null): static
    {
        $filtered = $callback
            ? array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH)
            : array_filter($this->items);
        return new static(array_values($filtered));
    }

    public function reject(callable $callback): static
    {
        return $this->filter(fn($v, $k) => !$callback($v, $k));
    }

    public function each(callable $callback): static
    {
        foreach ($this->items as $k => $v) {
            if ($callback($v, $k) === false) break;
        }
        return $this;
    }

    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    public function pluck(string $key): static
    {
        return new static(array_map(
            fn($item) => is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->$key ?? null) : null),
            $this->items
        ));
    }

    public function values(): static { return new static(array_values($this->items)); }
    public function keys(): static   { return new static(array_keys($this->items)); }

    public function sortBy(string|callable $key, bool $descending = false): static
    {
        $items = $this->items;
        usort($items, function ($a, $b) use ($key, $descending) {
            $va = is_callable($key) ? $key($a) : (is_array($a) ? ($a[$key] ?? null) : ($a->$key ?? null));
            $vb = is_callable($key) ? $key($b) : (is_array($b) ? ($b[$key] ?? null) : ($b->$key ?? null));
            return $descending ? $vb <=> $va : $va <=> $vb;
        });
        return new static($items);
    }

    public function sortByDesc(string|callable $key): static
    {
        return $this->sortBy($key, true);
    }

    public function groupBy(string|callable $key): static
    {
        $result = [];
        foreach ($this->items as $item) {
            $groupKey = is_callable($key) ? $key($item) : (is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null));
            $result[$groupKey][] = $item;
        }
        return new static(array_map(fn($g) => new static($g), $result));
    }

    public function chunk(int $size): static
    {
        return new static(array_map(fn($c) => new static($c), array_chunk($this->items, $size)));
    }

    public function unique(string $key = null): static
    {
        if ($key === null) {
            return new static(array_values(array_unique($this->items)));
        }
        $seen = [];
        return $this->filter(function ($item) use ($key, &$seen) {
            $k = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
            if (isset($seen[$k])) return false;
            return $seen[$k] = true;
        });
    }

    public function flatten(): static
    {
        $result = [];
        array_walk_recursive($this->items, function ($v) use (&$result) { $result[] = $v; });
        return new static($result);
    }

    public function merge(array|Collection $items): static
    {
        return new static(array_merge($this->items, is_array($items) ? $items : $items->all()));
    }

    public function take(int $limit): static
    {
        return new static(array_slice($this->items, 0, $limit));
    }

    public function skip(int $count): static
    {
        return new static(array_values(array_slice($this->items, $count)));
    }

    public function reverse(): static
    {
        return new static(array_values(array_reverse($this->items)));
    }

    // ── Aggregates ────────────────────────────────────────────────────────────

    public function sum(string|callable $key = null): int|float
    {
        if ($key === null) return array_sum($this->items);
        return $this->pluck($key)->sum();
    }

    public function avg(string|callable $key = null): float
    {
        $count = $this->count();
        return $count > 0 ? $this->sum($key) / $count : 0.0;
    }

    public function min(string $key = null): mixed
    {
        return $key ? $this->pluck($key)->min() : min($this->items);
    }

    public function max(string $key = null): mixed
    {
        return $key ? $this->pluck($key)->max() : max($this->items);
    }

    // ── Interfaces ────────────────────────────────────────────────────────────

    public function getIterator(): ArrayIterator { return new ArrayIterator($this->items); }
    public function jsonSerialize(): array        { return $this->items; }
    public function toJson(int $flags = 0): string
    {
        return (string) json_encode($this->items, $flags | JSON_THROW_ON_ERROR);
    }
}
