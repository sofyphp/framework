<?php

declare(strict_types=1);

namespace Sofy\Http;

/**
 * Cookie factory and queue.
 *
 * Usage:
 *   Cookie::queue('remember_me', $token, minutes: 60 * 24 * 30);
 *   Cookie::forget('remember_me');
 *
 * Queued cookies are flushed automatically when Response::send() is called,
 * or manually via Cookie::sendQueued().
 *
 * Reading is done via Request:
 *   $request->cookie('remember_me')
 */
class Cookie
{
    /** @var array<int, array<string, mixed>> */
    private static array $queued = [];

    // ── Factory ───────────────────────────────────────────────────────────────

    public static function make(
        string $name,
        string $value,
        int    $minutes  = 60,
        string $path     = '/',
        string $domain   = '',
        bool   $secure   = false,
        bool   $httpOnly = true,
        string $sameSite = 'Lax',
    ): array {
        return compact('name', 'value', 'minutes', 'path', 'domain', 'secure', 'httpOnly', 'sameSite');
    }

    // ── Queue ─────────────────────────────────────────────────────────────────

    public static function queue(
        string $name,
        string $value,
        int    $minutes  = 60,
        string $path     = '/',
        string $domain   = '',
        bool   $secure   = false,
        bool   $httpOnly = true,
        string $sameSite = 'Lax',
    ): void {
        self::$queued[] = self::make($name, $value, $minutes, $path, $domain, $secure, $httpOnly, $sameSite);
    }

    /** Queue a delete cookie (expires in the past). */
    public static function forget(string $name, string $path = '/'): void
    {
        self::$queued[] = self::make($name, '', -60 * 24 * 365, $path);
    }

    public static function getQueued(): array
    {
        return self::$queued;
    }

    public static function clearQueued(): void
    {
        self::$queued = [];
    }

    // ── Sending ───────────────────────────────────────────────────────────────

    public static function send(array $cookie): void
    {
        setcookie($cookie['name'], $cookie['value'], [
            'expires'  => time() + (int) $cookie['minutes'] * 60,
            'path'     => $cookie['path'],
            'domain'   => $cookie['domain'],
            'secure'   => $cookie['secure'],
            'httponly' => $cookie['httpOnly'],
            'samesite' => $cookie['sameSite'],
        ]);
    }

    /** Flush all queued cookies as Set-Cookie headers. */
    public static function sendQueued(): void
    {
        foreach (self::$queued as $cookie) {
            self::send($cookie);
        }
        self::$queued = [];
    }
}
