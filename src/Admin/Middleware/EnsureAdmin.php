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
            // Clear a dangling session (an _auth_id that no longer resolves to
            // a user) so we don't ping-pong with the login page.
            if (function_exists('auth') && auth()->check()) {
                auth()->logout();
            }
            // If the host app hasn't registered a login route yet, redirecting
            // to it would just 404. Show a clear setup hint so the operator
            // who just upgraded to v0.4.13 understands what to wire next.
            if (!$this->loginRouteExists($panel->loginUrl)) {
                return new Response($this->setupHint($panel), 503);
            }
            return Response::redirect($panel->loginUrl . '?next=' . urlencode($path));
        }

        if ($panel->requiredRole !== '' && method_exists($user, 'hasRole') && !$user->hasRole($panel->requiredRole)) {
            return new Response('Forbidden — admin role required.', 403);
        }

        return $next($request);
    }

    private function loginRouteExists(string $loginUrl): bool
    {
        try {
            $router = \Sofy\Core\Application::getInstance()->router();
            // Router::getRoutes() returns a FLAT list of ['method'=>, 'route'=>]
            // entries — not a method-keyed map. Descend into 'route' before
            // matching. (Pre-v0.5.2 this iterated the wrong shape and always
            // returned false; harmless only while no login route existed.)
            foreach ($router->getRoutes() as $entry) {
                $route = is_array($entry) ? ($entry['route'] ?? null) : null;
                if (is_object($route) && method_exists($route, 'matches') && $route->matches($loginUrl)) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return true; // boot trouble elsewhere — don't pile a 503 on top
        }
        return false;
    }

    private function setupHint(AdminPanel $panel): string
    {
        $loginUrl = htmlspecialchars($panel->loginUrl, ENT_QUOTES, 'UTF-8');
        return <<<HTML
        <!doctype html>
        <html><head><meta charset=utf-8><title>Admin login required</title>
        <style>
            body{font:14px/1.55 -apple-system,sans-serif;max-width:680px;margin:60px auto;padding:0 24px;color:#1d2330;background:#fbf9f3}
            h1{font-size:20px;margin:0 0 16px}
            h3{font-size:14px;margin:22px 0 6px}
            code{background:#f0e9d8;padding:2px 6px;border-radius:4px;font-family:Menlo,monospace;font-size:12.5px}
            pre{background:#1a1a1a;color:#e8e8e8;padding:14px;border-radius:8px;font-family:Menlo,monospace;font-size:12.5px;overflow:auto}
            .box{border-left:3px solid #ff6b5a;background:#fff4f0;padding:12px 16px;margin:12px 0;border-radius:0 6px 6px 0}
        </style></head><body>
        <h1>Admin auth required — no login route is registered</h1>
        <p>Sofy v0.4.13 flipped <code>requireAuth = true</code> for <code>/admin</code> by default after the security audit. Your app currently has no <code>$loginUrl</code> route, so this middleware can't redirect you anywhere useful.</p>
        <div class="box"><strong>Pick one</strong> in <code>bootstrap/app.php</code> before <code>\$app-&gt;boot()</code>:</div>
        <h3>Option A — keep auth on (recommended)</h3>
        <p>Register a POST/GET handler at <code>$loginUrl</code> and seed an admin user:</p>
        <pre>php sofy admin:create</pre>
        <h3>Option B — disable framework auth (only if gated upstream)</h3>
        <p>If <code>/admin</code> is already locked at nginx / Caddy / VPN level:</p>
        <pre>\\Sofy\\Admin\\Admin::panel()->requireAuth = false;</pre>
        <h3>Option C — change the loginUrl</h3>
        <pre>\\Sofy\\Admin\\Admin::panel()->loginUrl = '/sign-in';</pre>
        <p style="margin-top:32px;color:#7a7060;font-size:12.5px">See <code>docs/15-admin.md</code> for the full wiring guide.</p>
        </body></html>
        HTML;
    }
}
