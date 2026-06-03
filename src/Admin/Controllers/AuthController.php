<?php

declare(strict_types=1);

namespace Sofy\Admin\Controllers;

use Sofy\Admin\AdminPanel;
use Sofy\Auth\Auth;
use Sofy\Cache\Cache;
use Sofy\Http\Request;
use Sofy\Http\Response;
use Sofy\Support\Url;
use Sofy\View\UI;

/**
 * Built-in admin login. Shipped since v0.5.2 so the auth-by-default gate
 * introduced in v0.4.13 (requireAuth = true) is actually usable out of the
 * box — previously a fresh deploy hit a 503 "no login route" wall.
 *
 * Renders from the framework's own UI components (UI::page / UI::form /
 * UI::card / UI::alert) — same design system as the rest of the admin, no app
 * view file required. Verifies against Main\Models\User via Auth::attempt(),
 * enforces the panel's required
 * role, and throttles brute-force by IP+email. CSRF is handled by the global
 * CsrfMiddleware (the form embeds csrf_field()).
 *
 * Override by registering your own /admin/login route in routes/web.php — it
 * loads before admin-routes.php, so your handler wins.
 */
class AuthController
{
    private const int MAX_ATTEMPTS  = 5;
    private const int LOCKOUT_SECS   = 900; // 15 min

    public function showLogin(Request $request): Response
    {
        // Use the SAME "really authenticated" test EnsureAdmin uses — load the
        // user, not just the session flag. If we only checked Auth::check()
        // (session key present) here while EnsureAdmin checks Auth::user()
        // (loads the model), a dangling session — e.g. _auth_id present but no
        // loadable user — makes /admin/login redirect to /admin and /admin
        // redirect back, an infinite ERR_TOO_MANY_REDIRECTS loop.
        $user = Auth::user();
        if ($user !== null) {
            return Response::redirect('/admin');
        }

        // Session says authed but no user loaded → it's stale. Clear it so the
        // form is reachable instead of bouncing.
        if (Auth::check()) {
            Auth::logout();
        }

        $next = Url::sameOrigin((string) $request->input('next', ''), '/admin');
        return new Response($this->render($next), 200);
    }

    public function login(Request $request): Response
    {
        $email = trim((string) $request->input('email', ''));
        $pass  = (string) $request->input('password', '');
        $next  = Url::sameOrigin((string) $request->input('next', ''), '/admin');

        $throttleKey = 'admin_login:' . sha1($request->ip() . '|' . strtolower($email));

        if ($this->tooManyAttempts($throttleKey)) {
            return new Response(
                $this->render($next, 'Too many attempts. Wait a few minutes and try again.'),
                429,
            );
        }

        if ($email === '' || $pass === '') {
            return new Response($this->render($next, 'Enter your email and password.'), 422);
        }

        try {
            $ok = Auth::attempt(['email' => $email, 'password' => $pass]);
        } catch (\Throwable $e) {
            // Don't leak a stack to the browser, but DO log the real cause —
            // a generic "check the database" message hid an actual bug once.
            error_log('[sofy] admin login failed: ' . $e::class . ': ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine());
            return new Response(
                $this->render($next, 'Login is temporarily unavailable — see the server log for details.'),
                503,
            );
        }

        if (!$ok) {
            $this->hit($throttleKey);
            return new Response($this->render($next, 'Invalid email or password.'), 401);
        }

        // Enforce the configured admin role, if the user model supports roles.
        $panel = AdminPanel::instance();
        $user  = Auth::user();
        if ($panel->requiredRole !== '' && is_object($user)
            && method_exists($user, 'hasRole') && !$user->hasRole($panel->requiredRole)) {
            Auth::logout();
            $this->hit($throttleKey);
            return new Response(
                $this->render($next, 'This account does not have admin access.'),
                403,
            );
        }

        $this->clear($throttleKey);
        return Response::redirect($next);
    }

    public function logout(Request $request): Response
    {
        Auth::logout();
        return Response::redirect(AdminPanel::instance()->loginUrl);
    }

    // ── Throttle (best-effort; no-ops if cache is unavailable) ─────────────────

    private function tooManyAttempts(string $key): bool
    {
        try {
            return (int) Cache::get($key, 0) >= self::MAX_ATTEMPTS;
        } catch (\Throwable) {
            return false;
        }
    }

    private function hit(string $key): void
    {
        try {
            $n = (int) Cache::get($key, 0);
            Cache::set($key, $n + 1, self::LOCKOUT_SECS);
        } catch (\Throwable) {
            // cache down — fail open rather than lock everyone out
        }
    }

    private function clear(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (\Throwable) {
        }
    }

    // ── View ───────────────────────────────────────────────────────────────────

    /**
     * Render the login page from the framework's own UI components — same
     * design system the rest of the admin uses. The only hand-written bits
     * are a centering tweak via Page::css() (the sanctioned extension point)
     * and the brand line, which is itself markup stored on the panel.
     */
    private function render(string $next, ?string $error = null): string
    {
        $panel = AdminPanel::instance();
        $token = function_exists('csrf_token') ? csrf_token() : '';

        $form = UI::form($panel->loginUrl, 'POST')
            ->hidden('_token', $token)
            ->hidden('next', $next)
            ->email('Email', 'email', required: true)
            ->password('Password', 'password', required: true)
            ->submit('Sign in', 'primary');

        $brand = UI::raw(
            '<div class="sofy-login-brand">' . $panel->brand . '</div>'
            . '<div class="sofy-login-sub">Sign in to continue</div>',
        );

        $alert = $error !== null ? UI::alert($error, 'danger') : UI::raw('');

        return UI::page('Sign in · Admin')
            ->footer(false)
            ->add($brand, $alert, UI::card(null, $form))
            ->css(
                '.sofy-main{max-width:380px;margin:9vh auto}'
                . '.sofy-login-brand{text-align:center;font-size:21px;font-weight:700;letter-spacing:-.01em;margin:0 0 4px}'
                . '.sofy-login-brand span{color:var(--accent)}'
                . '.sofy-login-sub{text-align:center;color:var(--muted);font-size:13px;margin:0 0 18px}',
            )
            ->render();
    }
}
