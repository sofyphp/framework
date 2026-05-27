<?php

declare(strict_types=1);

namespace Sofy\Http\Middleware;

use Sofy\Http\Request;
use Sofy\Http\Response;
use Sofy\Http\Session;

class CsrfMiddleware implements MiddlewareInterface
{
    private const SAFE = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, callable $next): Response
    {
        if (!in_array($request->method(), self::SAFE, true)) {
            /** @var Session $session */
            $session  = session(); // reuses the shared singleton — no duplicate session_start()
            $token    = $request->input('_token') ?? $request->header('X-CSRF-Token') ?? '';
            $expected = $session->token();

            if (!hash_equals($expected, $token)) {
                return new Response('CSRF token mismatch.', 419);
            }
        }

        return $next($request);
    }
}
