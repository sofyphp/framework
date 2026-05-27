<?php

declare(strict_types=1);

namespace Sofy\Http\Middleware;

use Sofy\Auth\Auth;
use Sofy\Http\Request;
use Sofy\Http\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $redirect = '/login') {}

    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::check()) {
            if ($request->isAjax() || $request->isJson()) {
                return Response::json(['message' => 'Unauthenticated.'], 401);
            }
            return Response::redirect($this->redirect);
        }

        return $next($request);
    }
}
