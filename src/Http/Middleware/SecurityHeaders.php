<?php

declare(strict_types=1);

namespace Sofy\Http\Middleware;

use Sofy\Http\Request;
use Sofy\Http\Response;

/**
 * Default HTTP security headers. Wired into the router's global middleware
 * stack from Application::bootHttpMiddleware() — applies to every response,
 * GET and POST alike, /admin and /api.
 *
 * Defaults that should never bite anybody:
 *   X-Content-Type-Options: nosniff          (kills MIME-sniff XSS)
 *   X-Frame-Options: DENY                    (kills classic clickjacking)
 *   Referrer-Policy: strict-origin-when-cross-origin
 *   Permissions-Policy: camera=(), microphone=(), geolocation=()
 *
 * Defaults that the framework will only set if you didn't already:
 *   Content-Security-Policy — see csp() below. Allows inline styles + inline
 *   handlers because Sofy's UI components rely on both. Tighten in your
 *   own middleware once you've migrated to addEventListener everywhere.
 *
 * HTTPS-only, conditional on Request::isHttps():
 *   Strict-Transport-Security: max-age=2y; includeSubDomains
 *
 * Idempotent: never overwrites a header you've explicitly set elsewhere
 * (e.g. a per-route Response::withHeader('Content-Security-Policy', …)).
 */
class SecurityHeaders implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'        => 'DENY',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Content-Security-Policy' => $this->csp(),
        ];

        if ($request->isHttps()) {
            $defaults['Strict-Transport-Security'] = 'max-age=63072000; includeSubDomains';
        }

        foreach ($defaults as $name => $value) {
            if (!$response->hasHeader($name)) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }

    /**
     * Pragmatic CSP for the current Sofy UI: allows inline styles and inline
     * event handlers because UI components inline `<style>` blocks and
     * `onsubmit="return confirm(...)"`. Blocks third-party scripts, frames,
     * objects. Forms can only submit to same origin.
     */
    private function csp(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "img-src 'self' data: https:",
            "style-src 'self' 'unsafe-inline'",
            "script-src 'self' 'unsafe-inline'",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
    }
}
