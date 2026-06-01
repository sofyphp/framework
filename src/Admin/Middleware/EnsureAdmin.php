<?php

declare(strict_types=1);

namespace Sofy\Admin\Middleware;

use Sofy\Admin\AdminPanel;
use Sofy\Http\Request;
use Sofy\Http\Response;

/**
 * Gate for admin routes. No-ops unless Admin::useAuth() was called — when
 * enabled, an unauthenticated request is redirected to the login URL, and a
 * logged-in but unauthorised user gets a 403.
 *
 * Bring your own login form / role model — this middleware just checks
 * auth()->check() and (if the configured role is non-empty) the user's
 * hasRole($role) method.
 */
class EnsureAdmin
{
    public function handle(Request $request, callable $next): Response
    {
        $panel = AdminPanel::instance();

        if (!$panel->requireAuth) {
            return $next($request);
        }

        // Allow the login/logout routes through unconditionally.
        $path = $request->path();
        if ($path === $panel->loginUrl || $path === $panel->logoutUrl) {
            return $next($request);
        }

        $user = function_exists('auth') ? auth()->user() : null;

        if ($user === null) {
            return Response::redirect($panel->loginUrl . '?next=' . urlencode($path));
        }

        if ($panel->requiredRole !== '' && method_exists($user, 'hasRole') && !$user->hasRole($panel->requiredRole)) {
            return new Response('Forbidden — admin role required.', 403);
        }

        return $next($request);
    }
}
