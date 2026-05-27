<?php

declare(strict_types=1);

namespace Sofy\Support;

/**
 * URL generation helpers including signed and temporary URLs.
 *
 * Signed URLs embed an HMAC-SHA256 signature so they cannot be tampered with.
 * Temporary URLs also embed an expiry timestamp.
 *
 * Usage:
 *   $url = Url::signedRoute('verify-email', ['id' => 42]);
 *   $url = Url::temporarySignedRoute('download', ['file' => 'report.pdf'], expireInMinutes: 30);
 *
 *   // In middleware / controller:
 *   if (!Url::hasValidSignature($request)) abort(403);
 */
class Url
{
    // ── Generation ────────────────────────────────────────────────────────────

    public static function to(string $path, array $params = []): string
    {
        $base = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
        $url  = $base . '/' . ltrim($path, '/');

        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /** Generate a signed URL for a named route. */
    public static function signedRoute(string $route, array $params = []): string
    {
        if (function_exists('route')) {
            $url = route($route, $params);
        } else {
            $url = self::to($route, $params);
        }

        return self::sign($url);
    }

    /**
     * Generate a signed URL that expires after $minutes minutes.
     *
     * @param int $minutes Minutes until expiry
     */
    public static function temporarySignedRoute(string $route, array $params = [], int $minutes = 30): string
    {
        $params['expires'] = time() + $minutes * 60;

        if (function_exists('route')) {
            $url = route($route, $params);
        } else {
            $url = self::to($route, $params);
        }

        return self::sign($url);
    }

    // ── Verification ──────────────────────────────────────────────────────────

    public static function hasValidSignature(mixed $request): bool
    {
        $url = method_exists($request, 'uri') ? $request->uri() : (string) $request;

        // Extract signature from URL
        $parsed = parse_url($url);
        parse_str($parsed['query'] ?? '', $query);

        $signature = $query['signature'] ?? '';
        if (!$signature) return false;

        // Check expiry
        if (isset($query['expires']) && (int) $query['expires'] < time()) {
            return false;
        }

        // Rebuild URL without signature to verify
        unset($query['signature']);
        $base = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? '') . ($parsed['path'] ?? '');
        $urlWithoutSig = $query ? $base . '?' . http_build_query($query) : $base;

        return hash_equals(self::computeSignature($urlWithoutSig), $signature);
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private static function sign(string $url): string
    {
        $sig = self::computeSignature($url);
        $sep = str_contains($url, '?') ? '&' : '?';
        return $url . $sep . 'signature=' . $sig;
    }

    private static function computeSignature(string $url): string
    {
        $key = self::appKey();
        return hash_hmac('sha256', $url, $key);
    }

    private static function appKey(): string
    {
        $key = function_exists('config') ? config('app.key', '') : ($_ENV['APP_KEY'] ?? '');
        return (string) $key;
    }
}
