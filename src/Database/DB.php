<?php

declare(strict_types=1);

namespace Sofy\Database;

/**
 * Static facade for the default database connection.
 *
 * Usage:
 *   DB::table('users')->where('active', 1)->get();
 *   DB::transaction(fn() => ...);
 *   DB::raw('NOW()');
 */
class DB
{
    public static function table(string $table): QueryBuilder
    {
        return Connection::getDefault()->table($table);
    }

    public static function query(string $sql, array $bindings = []): array
    {
        return Connection::getDefault()->query($sql, $bindings);
    }

    public static function execute(string $sql, array $bindings = []): int
    {
        return Connection::getDefault()->execute($sql, $bindings);
    }

    public static function transaction(callable $callback): mixed
    {
        return Connection::getDefault()->transaction($callback);
    }

    public static function lastInsertId(): string|false
    {
        return Connection::getDefault()->lastInsertId();
    }

    public static function raw(string $expression): RawExpression
    {
        return new RawExpression($expression);
    }

    public static function connection(): Connection
    {
        return Connection::getDefault();
    }
}
