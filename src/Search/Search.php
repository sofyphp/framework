<?php

declare(strict_types=1);

namespace Sofy\Search;

/**
 * Static entry point to the search engine.
 *
 *   Search::query(Product::class, 'red router')->get();   // ranked models
 *   Search::index($product);                              // (re)index one
 *   Search::import(Product::class);                       // bulk index all
 *   Search::flush(Product::class);                        // clear an index
 *   Search::rank($options, $typed, fn($o) => $o->name);   // in-memory (components)
 *
 * The engine is built once from config('search') and cached. Tests can inject
 * a configured engine with Search::swap().
 */
final class Search
{
    private static ?Engine $engine = null;

    public static function engine(): Engine
    {
        if (self::$engine === null) {
            $config = function_exists('config') ? (array) config('search', []) : [];
            self::$engine = new Engine($config);
        }
        return self::$engine;
    }

    /** Replace the shared engine (tests / custom wiring). */
    public static function swap(Engine $engine): void
    {
        self::$engine = $engine;
    }

    public static function query(string $index, string $query, int $limit = 20, int $offset = 0): SearchResult
    {
        return self::engine()->search($index, $query, $limit, $offset);
    }

    public static function index(object $model): void
    {
        self::engine()->indexModel($model);
    }

    public static function unindex(object $model): void
    {
        self::engine()->unindexModel($model);
    }

    /** @param class-string $modelClass */
    public static function import(string $modelClass): int
    {
        return self::engine()->import($modelClass);
    }

    public static function flush(?string $index = null): void
    {
        self::engine()->flush($index);
    }

    /**
     * One-shot in-memory ranking — for UI components that already hold their
     * options and just need them ordered by what the user typed.
     *
     * @param iterable<int,mixed>    $items
     * @param callable(mixed):string $textOf
     * @return list<mixed>
     */
    public static function rank(iterable $items, string $query, callable $textOf, int $limit = 50): array
    {
        return self::engine()->rank($items, $query, $textOf, $limit);
    }
}
