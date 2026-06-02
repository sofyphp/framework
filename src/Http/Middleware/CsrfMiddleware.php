<?php

declare(strict_types=1);

namespace Sofy\Http\Middleware;

use Sofy\Http\Request;
use Sofy\Http\Response;
use Sofy\Http\Session;

/**
 * CSRF protection for state-changing HTTP methods. Wired into the
 * router's global middleware by Application::bootHttpMiddleware() since
 * v0.4.13 — until that release the middleware existed but was never
 * actually applied, and the security audit caught it.
 *
 * Exemptions:
 *  - GET / HEAD / OPTIONS — never checked (no state change)
 *  - /api/* paths — JSON APIs typically use Bearer tokens, not cookies;
 *    forcing _token there would just break clients. If your API DOES rely
 *    on cookie sessions, register CsrfMiddleware on the api group too.
 *
 * Token accepted from:
 *  - `_token` form field
 *  - `X-CSRF-Token` header (single-page apps)
 */
class CsrfMiddleware implements MiddlewareInterface
{
    private const array SAFE      = ['GET', 'HEAD', 'OPTIONS'];
    private const string API_PATH = '/api/';

    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->method(), self::SAFE, true)) {
            return $next($request);
        }

        // JSON APIs use Bearer tokens / API keys — not session cookies —
        // so the CSRF threat doesn't apply. Skip cleanly so /api/* keeps
        // working without _token plumbing.
        if (str_starts_with($request->path(), self::API_PATH)) {
            return $next($request);
        }

        /** @var Session $session */
        $session  = session(); // reuses the shared singleton — no duplicate session_start()
        $token    = $request->input('_token') ?? $request->header('X-CSRF-Token') ?? '';
        $expected = $session->token();

        if (!hash_equals($expected, $token)) {
            return new Response('CSRF token mismatch.', 419);
        }

        return $next($request);
    }
}
