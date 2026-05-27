<?php

declare(strict_types=1);

namespace Sofy\Support;

/**
 * Localization / translation facade.
 *
 * Language files live in lang/{locale}/{file}.php and return an array:
 *
 *   // lang/en/auth.php
 *   return [
 *       'failed'   => 'These credentials do not match our records.',
 *       'throttle' => 'Too many attempts. Please try again in :seconds seconds.',
 *   ];
 *
 * Usage:
 *   Lang::trans('auth.failed')
 *   trans('auth.throttle', ['seconds' => 60])
 *   trans()->choice('items.count', 3, ['count' => 3])
 */
class Lang
{
    private static string $locale   = 'en';
    private static string $fallback = 'en';

    /** @var array<string, array<string, mixed>> locale → file → lines */
    private static array $loaded = [];

    public static function setLocale(string $locale): void
    {
        self::$locale = $locale;
    }

    public static function getLocale(): string
    {
        return self::$locale;
    }

    public static function setFallback(string $locale): void
    {
        self::$fallback = $locale;
    }

    public static function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale ??= self::$locale;

        $line = self::getLine($key, $locale)
            ?? self::getLine($key, self::$fallback)
            ?? $key;

        return self::makeReplacements((string) $line, $replace);
    }

    /**
     * Simple pluralization: "One item|:count items"
     * Uses count === 1 → first segment, otherwise second.
     */
    public static function choice(string $key, int $count, array $replace = [], ?string $locale = null): string
    {
        $line  = self::trans($key, $replace, $locale);
        $parts = explode('|', $line, 2);

        $chosen = $count === 1 ? ($parts[0] ?? $line) : ($parts[1] ?? $line);
        return self::makeReplacements(trim($chosen), array_merge(['count' => $count], $replace));
    }

    public static function has(string $key, ?string $locale = null): bool
    {
        return self::getLine($key, $locale ?? self::$locale) !== null;
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private static function getLine(string $key, string $locale): string|array|null
    {
        // key format: "file.item" or "file.nested.item"
        $dotPos = strpos($key, '.');
        if ($dotPos === false) {
            return null;
        }

        $file = substr($key, 0, $dotPos);
        $item = substr($key, $dotPos + 1);

        self::load($file, $locale);

        $lines = self::$loaded[$locale][$file] ?? [];

        foreach (explode('.', $item) as $segment) {
            if (!is_array($lines) || !array_key_exists($segment, $lines)) {
                return null;
            }
            $lines = $lines[$segment];
        }

        return $lines !== [] ? $lines : null;
    }

    private static function load(string $file, string $locale): void
    {
        if (isset(self::$loaded[$locale][$file])) {
            return;
        }

        $path = function_exists('base_path')
            ? base_path("lang/$locale/$file.php")
            : "lang/$locale/$file.php";

        self::$loaded[$locale][$file] = file_exists($path) ? (array) require $path : [];
    }

    private static function makeReplacements(string $line, array $replace): string
    {
        foreach ($replace as $key => $value) {
            $line = str_replace(':' . $key, (string) $value, $line);
        }
        return $line;
    }
}
