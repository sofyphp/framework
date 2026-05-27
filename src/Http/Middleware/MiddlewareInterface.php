<?php

declare(strict_types=1);

namespace Sofy\Http\Middleware;

use Sofy\Http\Request;
use Sofy\Http\Response;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
