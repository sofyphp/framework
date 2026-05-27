<?php

declare(strict_types=1);

namespace Sofy\Security;

use RuntimeException;

class Hash
{
    private static string $algorithm = PASSWORD_BCRYPT;
    private static array  $options   = ['cost' => 12];

    // ── Configuration ──────────────────────────────────────────────────────────

    public static function useArgon2i(array $options = []): void
    {
        static::$algorithm = PASSWORD_ARGON2I;
        static::$options   = $options;
    }

    public static function useArgon2id(array $options = []): void
    {
        static::$algorithm = PASSWORD_ARGON2ID;
        static::$options   = $options;
    }

    public static function useBcrypt(int $cost = 12): void
    {
        static::$algorithm = PASSWORD_BCRYPT;
        static::$options   = ['cost' => $cost];
    }

    // ── Core ───────────────────────────────────────────────────────────────────

    public static function make(string $value, array $options = []): string
    {
        return password_hash($value, static::$algorithm, array_merge(static::$options, $options));
    }

    public static function check(string $value, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }
        return password_verify($value, $hash);
    }

    public static function needsRehash(string $hash, array $options = []): bool
    {
        return password_needs_rehash($hash, static::$algorithm, array_merge(static::$options, $options));
    }

    public static function isHashed(string $value): bool
    {
        return password_get_info($value)['algo'] !== null;
    }

    // ── HMAC / raw ─────────────────────────────────────────────────────────────

    public static function hmac(string $value, string $key, string $algo = 'sha256'): string
    {
        return hash_hmac($algo, $value, $key);
    }

    public static function sha256(string $value): string
    {
        return hash('sha256', $value);
    }

    public static function md5(string $value, bool $raw = false): string
    {
        return md5($value, $raw);
    }
}
