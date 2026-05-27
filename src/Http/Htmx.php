<?php

declare(strict_types=1);

namespace Sofy\Http;

/**
 * HTMX — server-side helpers for HTMX-powered partial rendering.
 *
 * Include HTMX in a page:
 *   UI::page('Title')->withHtmx()->add(...)
 *
 * Detect & respond to HTMX requests in a controller:
 *
 *   public function list(Request $request): Response
 *   {
 *       $users = User::all();
 *
 *       if (Htmx::isRequest()) {
 *           return Htmx::partial(UI::table(['ID','Name'], $users, ['id','name']));
 *       }
 *
 *       return UI::page('Users')
 *           ->withHtmx()
 *           ->add(
 *               UI::button('Refresh', '/users', 'ghost')
 *                   ->hx('hx-get', '/users')
 *                   ->hx('hx-target', '#user-list')
 *                   ->hx('hx-swap', 'innerHTML'),
 *               UI::raw('<div id="user-list">'),
 *               UI::table(['ID','Name'], $users, ['id','name']),
 *               UI::raw('</div>'),
 *           )
 *           ->response();
 *   }
 */
class Htmx
{
    // ── Request detection ─────────────────────────────────────────────────────

    /** Returns true when the request was made by HTMX (HX-Request header present). */
    public static function isRequest(): bool
    {
        return ($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true';
    }

    /** The id of the target element from the HX-Target header, or null. */
    public static function target(): ?string
    {
        return $_SERVER['HTTP_HX_TARGET'] ?? null;
    }

    /** The id of the element that triggered the request (HX-Trigger), or null. */
    public static function triggerId(): ?string
    {
        return $_SERVER['HTTP_HX_TRIGGER'] ?? null;
    }

    /** The name of the element that triggered the request (HX-Trigger-Name), or null. */
    public static function triggerName(): ?string
    {
        return $_SERVER['HTTP_HX_TRIGGER_NAME'] ?? null;
    }

    /** The current URL from the browser (HX-Current-URL), or null. */
    public static function currentUrl(): ?string
    {
        return $_SERVER['HTTP_HX_CURRENT_URL'] ?? null;
    }

    // ── Partial responses ─────────────────────────────────────────────────────

    /**
     * Return only the rendered component HTML — no <html>/<head>/<body> wrapper.
     * Perfect for HTMX hx-swap responses.
     *
     * @param mixed $content  string | Component | Component[]
     */
    public static function partial(mixed $content, int $status = 200, array $headers = []): Response
    {
        if (is_array($content)) {
            $html = implode('', array_map(fn($c) => (string) $c, $content));
        } else {
            $html = (string) $content;
        }

        return new Response($html, $status, $headers);
    }

    // ── Response headers ──────────────────────────────────────────────────────

    /**
     * Tell HTMX to do a client-side redirect after the response.
     * Call before returning a Response.
     */
    public static function redirect(string $url): void
    {
        header('HX-Redirect: ' . $url);
    }

    /**
     * Tell HTMX to do a full page refresh.
     */
    public static function refresh(): void
    {
        header('HX-Refresh: true');
    }

    /**
     * Override the target element from the server side.
     */
    public static function retarget(string $cssSelector): void
    {
        header('HX-Retarget: ' . $cssSelector);
    }

    /**
     * Override the swap method from the server side.
     * Values: innerHTML | outerHTML | beforebegin | afterbegin | beforeend | afterend | delete | none
     */
    public static function reswap(string $strategy): void
    {
        header('HX-Reswap: ' . $strategy);
    }

    /**
     * Push a URL into the browser history.
     */
    public static function pushUrl(string $url): void
    {
        header('HX-Push-Url: ' . $url);
    }

    /**
     * Trigger a client-side event after the response is processed.
     *
     * @param string     $event  Event name
     * @param mixed|null $detail Optional JSON-serialisable detail payload
     * @throws \JsonException if $detail cannot be JSON-encoded
     */
    public static function triggerEvent(string $event, mixed $detail = null): void
    {
        if ($detail === null) {
            header('HX-Trigger: ' . $event);
        } else {
            header('HX-Trigger: ' . json_encode([$event => $detail], JSON_THROW_ON_ERROR));
        }
    }
}
