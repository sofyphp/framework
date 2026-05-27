<?php

declare(strict_types=1);

namespace Sofy\Feature;

/**
 * Feature Flags — enable/disable features per user, percentage, or environment.
 *
 * Define flags at bootstrap (e.g. in routes/bootstrap.php):
 *
 *   Flag::define('new-dashboard', true);
 *   Flag::define('beta-charts',   Flag::percent(20));   // 20% of users
 *   Flag::define('admin-tools',   Flag::users([1, 2])); // specific user IDs
 *   Flag::define('maintenance',   Flag::env('production'));
 *
 * Check in controllers or views:
 *
 *   if (Flag::enabled('new-dashboard')) { ... }
 *   if (Flag::for($user)->enabled('beta-charts')) { ... }
 */
class Flag
{
    /** @var array<string, bool|callable> */
    private static array $flags = [];

    // ── Define ────────────────────────────────────────────────────────────────

    /**
     * Define (or redefine) a feature flag.
     *
     * @param bool|callable(mixed):bool $value  Static bool or a callable receiving $user
     */
    public static function define(string $name, bool|callable $value): void
    {
        self::$flags[$name] = $value;
    }

    // ── Check ─────────────────────────────────────────────────────────────────

    /**
     * Check whether a flag is enabled, optionally for a given user.
     * Returns false for undefined flags.
     */
    public static function enabled(string $name, mixed $user = null): bool
    {
        if (!array_key_exists($name, self::$flags)) {
            return false;
        }

        $flag = self::$flags[$name];

        return is_bool($flag) ? $flag : (bool) ($flag)($user);
    }

    /**
     * Bind a user for fluent checks:
     *
     *   Flag::for($user)->enabled('beta-charts')
     */
    public static function for(mixed $user): FlagContext
    {
        return new FlagContext($user);
    }

    // ── Factory helpers ───────────────────────────────────────────────────────

    /**
     * Percentage-based rollout (deterministic per user).
     * Uses crc32(userId:flagName) so the same user always gets the same result.
     *
     *   Flag::define('beta', Flag::percent(10)); // enabled for ~10% of users
     */
    public static function percent(int $pct): \Closure
    {
        return function (mixed $user) use ($pct): bool {
            if ($pct <= 0) {
                return false;
            }
            if ($pct >= 100) {
                return true;
            }
            $id   = is_object($user) ? ($user->id ?? spl_object_id($user)) : (string) $user;
            $seed = abs(crc32($id . ':__flag__'));
            return ($seed % 100) < $pct;
        };
    }

    /**
     * Enable only for specific user IDs.
     *
     *   Flag::define('admin-tools', Flag::users([1, 2, 5]));
     */
    public static function users(array $ids): \Closure
    {
        return function (mixed $user) use ($ids): bool {
            $id = is_object($user) ? ($user->id ?? null) : $user;
            return in_array($id, $ids, strict: true);
        };
    }

    /**
     * Enable only in specific APP_ENV values.
     *
     *   Flag::define('maintenance', Flag::env('production'));
     *   Flag::define('dev-tools',   Flag::env('local', 'testing'));
     */
    public static function env(string ...$envs): \Closure
    {
        return function () use ($envs): bool {
            $current = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';
            return in_array($current, $envs, strict: true);
        };
    }

    /**
     * Combine multiple conditions with AND logic.
     *
     *   Flag::define('power-beta', Flag::all(Flag::percent(50), Flag::users([1])));
     */
    public static function all(callable ...$conditions): \Closure
    {
        return function (mixed $user) use ($conditions): bool {
            foreach ($conditions as $condition) {
                if (!($condition)($user)) {
                    return false;
                }
            }
            return true;
        };
    }

    /**
     * Combine multiple conditions with OR logic.
     */
    public static function any(callable ...$conditions): \Closure
    {
        return function (mixed $user) use ($conditions): bool {
            foreach ($conditions as $condition) {
                if (($condition)($user)) {
                    return true;
                }
            }
            return false;
        };
    }

    // ── Introspection ─────────────────────────────────────────────────────────

    /** Return all defined flag names. */
    public static function names(): array
    {
        return array_keys(self::$flags);
    }

    /** Remove all defined flags (useful in tests). */
    public static function flush(): void
    {
        self::$flags = [];
    }
}
