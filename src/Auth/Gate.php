<?php

declare(strict_types=1);

namespace Sofy\Auth;

use Sofy\Http\HttpException;

/**
 * Simple authorization gate.
 *
 * Usage — define abilities:
 *   Gate::define('edit-post', fn($user, $post) => $user->id === $post->user_id);
 *
 * Usage — register policies:
 *   Gate::policy(Post::class, PostPolicy::class);
 *
 * Usage — check:
 *   Gate::allows('edit-post', $post)   // bool
 *   Gate::authorize('edit-post', $post) // throws 403
 *   Auth::can('edit-post', $post)       // alias
 */
class Gate
{
    /** @var array<string, callable> */
    private static array $abilities = [];

    /** @var array<class-string, class-string> model → policy */
    private static array $policies = [];

    // ── Registration ──────────────────────────────────────────────────────────

    public static function define(string $ability, callable $callback): void
    {
        self::$abilities[$ability] = $callback;
    }

    public static function policy(string $model, string $policy): void
    {
        self::$policies[$model] = $policy;
    }

    // ── Checks ────────────────────────────────────────────────────────────────

    public static function allows(string $ability, mixed $arguments = null): bool
    {
        return self::inspect(Auth::user(), $ability, $arguments);
    }

    public static function denies(string $ability, mixed $arguments = null): bool
    {
        return !self::allows($ability, $arguments);
    }

    public static function any(array $abilities, mixed $arguments = null): bool
    {
        foreach ($abilities as $ability) {
            if (self::allows($ability, $arguments)) return true;
        }
        return false;
    }

    public static function none(array $abilities, mixed $arguments = null): bool
    {
        return !self::any($abilities, $arguments);
    }

    /** @throws HttpException 403 when not authorized */
    public static function authorize(string $ability, mixed $arguments = null): void
    {
        if (!self::allows($ability, $arguments)) {
            throw new HttpException(403, 'This action is unauthorized.');
        }
    }

    /** Check as a specific user — does not use Auth::user(). */
    public static function forUser(mixed $user): GateProxy
    {
        return new GateProxy($user, self::$abilities, self::$policies);
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private static function inspect(mixed $user, string $ability, mixed $arguments): bool
    {
        if ($user === null) {
            return false;
        }

        // 1. Super-admin / before hook
        if (isset(self::$abilities['before'])) {
            $result = (self::$abilities['before'])($user, $ability);
            if ($result !== null) {
                return (bool) $result;
            }
        }

        // 2. Policy method
        $model = is_object($arguments) ? $arguments::class : (is_string($arguments) ? $arguments : null);
        if ($model !== null && isset(self::$policies[$model])) {
            $policy = new self::$policies[$model]();
            if (method_exists($policy, $ability)) {
                return (bool) $policy->$ability($user, $arguments);
            }
        }

        // 3. Direct ability definition
        if (isset(self::$abilities[$ability])) {
            return (bool) (self::$abilities[$ability])($user, $arguments);
        }

        return false;
    }
}
