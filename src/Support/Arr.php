<?php

declare(strict_types=1);

namespace Sofy\Support;

class Arr
{
    // ── Dot-notation access ───────────────────────────────────────────────────

    public static function get(array $array, string|int $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (!str_contains((string) $key, '.')) {
            return $default;
        }

        foreach (explode('.', (string) $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    public static function set(array &$array, string $key, mixed $value): array
    {
        $keys = explode('.', $key);
        $ref  = &$array;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $ref[$segment] = $value;
            } else {
                if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                    $ref[$segment] = [];
                }
                $ref = &$ref[$segment];
            }
        }

        return $array;
    }

    public static function has(array $array, string|array $keys): bool
    {
        foreach ((array) $keys as $key) {
            $sub = $array;
            foreach (explode('.', $key) as $segment) {
                if (!is_array($sub) || !array_key_exists($segment, $sub)) {
                    return false;
                }
                $sub = $sub[$segment];
            }
        }
        return true;
    }

    public static function forget(array &$array, string|array $keys): void
    {
        foreach ((array) $keys as $key) {
            $parts = explode('.', $key);
            $last  = array_pop($parts);
            $ref   = &$array;
            foreach ($parts as $segment) {
                if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                    continue 2;
                }
                $ref = &$ref[$segment];
            }
            unset($ref[$last]);
        }
    }

    public static function pull(array &$array, string $key, mixed $default = null): mixed
    {
        $value = static::get($array, $key, $default);
        static::forget($array, $key);
        return $value;
    }

    // ── Filtering / selection ─────────────────────────────────────────────────

    public static function only(array $array, array|string $keys): array
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }

    public static function except(array $array, array|string $keys): array
    {
        return array_diff_key($array, array_flip((array) $keys));
    }

    public static function where(array $array, callable $callback): array
    {
        return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }

    public static function whereNotNull(array $array): array
    {
        return array_filter($array, fn($v) => $v !== null);
    }

    public static function whereNull(array $array): array
    {
        return array_filter($array, fn($v) => $v === null);
    }

    public static function first(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            if (empty($array)) {
                return $default;
            }
            return reset($array);
        }
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }
        return $default;
    }

    public static function last(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($array) ? $default : end($array);
        }
        return static::first(array_reverse($array, true), $callback, $default);
    }

    // ── Flatten / nest ────────────────────────────────────────────────────────

    public static function flatten(array $array, float $depth = INF): array
    {
        $result = [];
        foreach ($array as $item) {
            if (!is_array($item)) {
                $result[] = $item;
            } elseif ($depth === 1) {
                $result = array_merge($result, array_values($item));
            } else {
                $result = array_merge($result, static::flatten($item, $depth - 1));
            }
        }
        return $result;
    }

    public static function collapse(array $array): array
    {
        $results = [];
        foreach ($array as $values) {
            if (!is_array($values)) {
                continue;
            }
            $results[] = $values;
        }
        return array_merge([], ...$results);
    }

    public static function dot(array $array, string $prepend = ''): array
    {
        $results = [];
        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $results = array_merge($results, static::dot($value, $prepend . $key . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }
        return $results;
    }

    public static function undot(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            static::set($result, $key, $value);
        }
        return $result;
    }

    // ── Transform ─────────────────────────────────────────────────────────────

    public static function pluck(array $array, string $value, ?string $key = null): array
    {
        $results = [];
        foreach ($array as $item) {
            $itemValue = is_array($item) ? ($item[$value] ?? null) : (is_object($item) ? ($item->$value ?? null) : null);
            if ($key !== null) {
                $itemKey = is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->$key ?? null) : null);
                $results[$itemKey] = $itemValue;
            } else {
                $results[] = $itemValue;
            }
        }
        return $results;
    }

    public static function groupBy(array $array, string|callable $key): array
    {
        $results = [];
        foreach ($array as $item) {
            $groupKey = is_callable($key)
                ? $key($item)
                : (is_array($item) ? ($item[$key] ?? '') : (is_object($item) ? ($item->$key ?? '') : ''));
            $results[$groupKey][] = $item;
        }
        return $results;
    }

    public static function keyBy(array $array, string|callable $key): array
    {
        $results = [];
        foreach ($array as $item) {
            $itemKey = is_callable($key)
                ? $key($item)
                : (is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->$key ?? null) : null));
            $results[$itemKey] = $item;
        }
        return $results;
    }

    public static function mapWithKeys(array $array, callable $callback): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $assoc = $callback($value, $key);
            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }
        return $result;
    }

    public static function map(array $array, callable $callback): array
    {
        return array_map($callback, $array);
    }

    // ── Sorting ───────────────────────────────────────────────────────────────

    public static function sortBy(array $array, string|callable $key, bool $descending = false): array
    {
        usort($array, function ($a, $b) use ($key, $descending) {
            $aVal = is_callable($key) ? $key($a) : (is_array($a) ? ($a[$key] ?? null) : (is_object($a) ? ($a->$key ?? null) : null));
            $bVal = is_callable($key) ? $key($b) : (is_array($b) ? ($b[$key] ?? null) : (is_object($b) ? ($b->$key ?? null) : null));
            $cmp  = $aVal <=> $bVal;
            return $descending ? -$cmp : $cmp;
        });
        return $array;
    }

    public static function sortKeys(array $array, bool $descending = false): array
    {
        $descending ? krsort($array) : ksort($array);
        return $array;
    }

    // ── Set operations ────────────────────────────────────────────────────────

    public static function unique(array $array, ?string $key = null): array
    {
        if ($key === null) {
            return array_unique($array);
        }
        $seen   = [];
        $result = [];
        foreach ($array as $item) {
            $v = is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->$key ?? null) : null);
            if (!in_array($v, $seen, true)) {
                $seen[]   = $v;
                $result[] = $item;
            }
        }
        return $result;
    }

    // ── Utility ───────────────────────────────────────────────────────────────

    public static function wrap(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        return is_array($value) ? $value : [$value];
    }

    public static function prepend(array $array, mixed $value, mixed $key = null): array
    {
        if ($key === null) {
            array_unshift($array, $value);
        } else {
            $array = [$key => $value] + $array;
        }
        return $array;
    }

    public static function divide(array $array): array
    {
        return [array_keys($array), array_values($array)];
    }

    public static function chunk(array $array, int $size): array
    {
        return array_chunk($array, $size, true);
    }

    public static function zip(array ...$arrays): array
    {
        return array_map(null, ...$arrays);
    }

    public static function shuffle(array $array): array
    {
        shuffle($array);
        return $array;
    }

    public static function random(array $array, int $number = 1): mixed
    {
        if ($number === 1) {
            return $array[array_rand($array)];
        }
        $keys = array_rand($array, $number);
        return array_intersect_key($array, array_flip($keys));
    }

    public static function isList(array $array): bool
    {
        return array_is_list($array);
    }
}
