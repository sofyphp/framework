<?php

declare(strict_types=1);

namespace Sofy\Http\Middleware;

use Sofy\Http\Request;
use Sofy\Http\Response;

class CorsMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->withCorsHeaders(new Response('', 204), $request);
        }

        return $this->withCorsHeaders($next($request), $request);
    }

    private function withCorsHeaders(Response $response, Request $request): Response
    {
        $allowedOrigins = (array) (function_exists('config') ? config('cors.allowed_origins', ['*']) : ['*']);
        $origin         = $_SERVER['HTTP_ORIGIN'] ?? null;

        if (in_array('*', $allowedOrigins, true)) {
            $allowed = '*';
        } elseif ($origin && in_array($origin, $allowedOrigins, true)) {
            $allowed = $origin;
        } else {
            return $response;
        }

        $methods = implode(', ', (array) (function_exists('config')
            ? config('cors.allowed_methods', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'])
            : ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']));

        $headers = implode(', ', (array) (function_exists('config')
            ? config('cors.allowed_headers', ['Content-Type', 'Authorization', 'X-Requested-With'])
            : ['Content-Type', 'Authorization', 'X-Requested-With']));

        $maxAge = (string) (function_exists('config') ? config('cors.max_age', 86400) : 86400);

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowed)
            ->withHeader('Access-Control-Allow-Methods', $methods)
            ->withHeader('Access-Control-Allow-Headers', $headers)
            ->withHeader('Access-Control-Max-Age', $maxAge);

        if (function_exists('config') && config('cors.supports_credentials', false)) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        if ($allowed !== '*' && $origin) {
            $response = $response->withHeader('Vary', 'Origin');
        }

        return $response;
    }
}
