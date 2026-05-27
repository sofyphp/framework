<?php

declare(strict_types=1);

namespace Sofy\Http\Middleware;

use Sofy\Auth\Auth;
use Sofy\Http\Request;
use Sofy\Http\Response;

class GuestMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $redirect = '/') {}

    public function handle(Request $request, callable $next): Response
    {
        if (Auth::check()) {
            return Response::redirect($this->redirect);
        }

        return $next($request);
    }
}
