<?php

declare(strict_types=1);

namespace Sofy\Auth;

use Sofy\Http\Session;

/**
 * Класс Auth не привязан к конкретной модели пользователя.
 * По умолчанию предполагается Main\Models\User, но можно передать любой класс.
 */
class Auth
{
    private static ?Session $session = null;

    /** In-memory user cache — avoids repeated DB lookups for Auth::user() per request. */
    private static mixed $cachedUser   = null;
    private static ?int  $cachedUserId = null;

    private static function session(): Session
    {
        if (self::$session === null) {
            self::$session = new Session();
            self::$session->start();
        }
        return self::$session;
    }

    /**
     * Пытается войти по credentials.
     * По умолчанию ищет пользователя в Main\Models\User.
     *
     * @param array{email: string, password: string} $credentials
     */
    public static function attempt(array $credentials, string $model = 'Main\\Models\\User'): bool
    {
        $email    = $credentials['email']    ?? null;
        $password = $credentials['password'] ?? null;

        if (!$email || !$password) return false;

        // Builder::first() returns a hydrated Model, not an array — use object
        // access. (The old $user['password'] form threw "Cannot use object as
        // array" on every match; it only surfaced once a login form started
        // calling attempt() in v0.5.2.)
        $user = $model::where('email', $email)->first();
        if ($user === null) {
            return false;
        }

        $hash = is_object($user) ? (string) $user->getAttribute('password') : (string) ($user['password'] ?? '');
        if (!password_verify($password, $hash)) {
            return false;
        }

        $id = is_object($user) ? $user->getPrimaryKeyValue() : ($user['id'] ?? 0);
        static::loginById((int) $id, $model);
        return true;
    }

    /** Авторизует пользователя по объекту/массиву. */
    public static function login(object|array $user): void
    {
        $id    = is_array($user) ? ($user['id'] ?? null) : ($user->id ?? null);
        $class = is_object($user) ? get_class($user) : 'Main\\Models\\User';
        static::loginById((int) $id, $class);
    }

    public static function logout(): void
    {
        static::session()->forget('_auth_id');
        static::session()->forget('_auth_class');
        static::session()->regenerate();
        self::$cachedUser   = null;
        self::$cachedUserId = null;
    }

    public static function check(): bool
    {
        return static::session()->has('_auth_id');
    }

    public static function guest(): bool
    {
        return !static::check();
    }

    public static function id(): int|null
    {
        $id = static::session()->get('_auth_id');
        return $id !== null ? (int) $id : null;
    }

    public static function user(): mixed
    {
        $id    = static::id();
        $class = static::session()->get('_auth_class', 'Main\\Models\\User');

        if (!$id || !class_exists($class)) {
            return null;
        }

        // Return cached instance if the same user ID is still active
        if ($id === self::$cachedUserId && self::$cachedUser !== null) {
            return self::$cachedUser;
        }

        self::$cachedUserId = $id;
        return self::$cachedUser = $class::find($id);
    }

    public static function can(string $ability, mixed $arguments = null): bool
    {
        return Gate::allows($ability, $arguments);
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private static function loginById(int $id, string $class): void
    {
        static::session()->regenerate();
        static::session()->set('_auth_id', $id);
        static::session()->set('_auth_class', $class);
    }
}
