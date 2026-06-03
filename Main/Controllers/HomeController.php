<?php

declare(strict_types=1);

namespace Main\Controllers;

use Sofy\Http\Cookie;
use Sofy\Http\Request;
use Sofy\Http\Response;
use Sofy\Support\Url;

/**
 * Scaffold home + utility routes. These were closures in routes/web.php until
 * v0.5.0 — moved to a controller so `php sofy route:cache` can serialize the
 * routing table (closures can't be cached). Keep new routes controller-based
 * to stay cacheable.
 */
class HomeController extends Controller
{
    public function welcome(): Response
    {
        return $this->view('welcome');
    }

    /** Demo route that intentionally throws, to preview the debug page. */
    public function debugError(): never
    {
        throw new \RuntimeException('Test exception from Sofy debug page');
    }

    /**
     * Switch UI locale via cookie, then bounce back to the referring page.
     * Redirect target is validated same-origin to avoid an open redirect.
     */
    public function setLocale(Request $request, string $locale): Response
    {
        if (!in_array($locale, ['en', 'ru'], true)) {
            $locale = 'en';
        }
        Cookie::queue('app_locale', $locale, minutes: 60 * 24 * 365);

        $back = Url::sameOrigin($_SERVER['HTTP_REFERER'] ?? '/', '/');
        return Response::redirect($back);
    }
}
