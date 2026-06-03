# Performance & production tuning

Sofy boots in **under 1 ms** with a warm opcache and serves a request in
~1–2 ms TTFB out of the box. You rarely need to make a single request
faster — there's almost nothing left to cut. Where real, *multiplicative*
gains live is **throughput** under concurrency: stop every request from
re-doing work that never changes between requests.

This page covers the three levers, in order of impact.

---

## TL;DR — one command + one php.ini block

```bash
# On the server, after deploying code (or editing .env):
composer dump-autoload --optimize --classmap-authoritative
php sofy optimize
sudo systemctl reload php8.5-fpm
```

```ini
; /etc/php/8.5/fpm/conf.d/99-sofy.ini
opcache.enable=1
opcache.preload=/var/www/sofy-app/bootstrap/cache/preload.php
opcache.preload_user=www-data
opcache.validate_timestamps=0       ; prod only — see note
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.jit=tracing
opcache.jit_buffer_size=64M
```

For local development, undo the app-side caches with:

```bash
php sofy optimize:clear
```

---

## Lever 1 — opcache preload (biggest win)

Without preload, every PHP-FPM worker compiles each framework class the
first time it touches it — per worker, after every reload. `opcache.preload`
compiles the whole framework **once** at master start into shared memory;
all workers inherit ready-to-run classes with zero compile and zero file
`stat()` per request. On real apps this is commonly a **1.3–2× throughput**
improvement.

`php sofy optimize` generates `bootstrap/cache/preload.php` — a self-locating
script that compiles every `*.php` under `src/`, `app/`, `Main/`, `modules/`
and `config/`. Point `opcache.preload` at it and reload FPM.

> Regenerate the preload script (`php sofy optimize`) and reload FPM after
> every deploy. The compiled classes are a snapshot of the code at generate
> time.

## Lever 2 — `opcache.validate_timestamps=0` (prod only)

By default opcache `stat()`s every PHP file on every request to check if it
changed. In production your code doesn't change between deploys, so this is
pure waste. Setting `validate_timestamps=0` removes the stat calls entirely.

**The catch:** opcache will no longer notice edited files. You **must**
reload FPM after each deploy (`systemctl reload php8.5-fpm`) or your changes
won't take effect. Never set this on a box where you edit code live.

## Lever 3 — route & config caches (`php sofy optimize`)

Two of the framework's per-request costs are pure repetition:

- **Routes** — without a cache, every request requires each `routes.php`
  (app + every module) and reconstructs every `Route`, recompiling its URL
  regex. That cost is `O(number of routes)`. `php sofy route:cache`
  serializes the fully-built routing table (compiled regexes included) to
  `bootstrap/cache/routes.php`; `Application::boot()` restores it and skips
  the rebuild.

- **Config** — without a cache, the first `config('x.y')` for each file does
  a `require` + `stat`. `php sofy config:cache` pre-merges every
  `config/*.php` (with `env()` already resolved) into one file loaded once at
  construct.

`php sofy optimize` runs both, plus the preload generator.

### Route cache requires controller actions, not closures

Closures can't be serialized, so a route defined as a closure can't be
cached — and **one closure route disables the entire route cache** (it's
all-or-nothing). `php sofy optimize` detects this, lists the offending
routes, and skips *only* route caching (config + preload still apply):

```
  • route:cache
    skipped — these routes use Closure actions (not cacheable):
        GET /demo
    Convert them to [Controller::class, 'method'] actions, then re-run.
```

Convert closures to controllers to unlock route caching:

```php
// before — not cacheable
$router->get('/', fn() => view('welcome'));

// after — cacheable
$router->get('/', [HomeController::class, 'welcome']);
```

The shipped scaffold (welcome page, language switch, API ping, Demo module)
is already controller-based as of v0.5.0, so a fresh app caches cleanly.

---

## Composer autoloader

Already configured (`optimize-autoloader: true` in composer.json). On the
server, go one step further with an authoritative classmap so the autoloader
never hits the filesystem looking for a class:

```bash
composer dump-autoload --optimize --classmap-authoritative --no-dev
```

`--classmap-authoritative` means "if it's not in the classmap, it doesn't
exist" — don't use it if you generate classes at runtime.

---

## Measuring it for real

CLI benchmarks (a warm `php -r` loop) **cannot** show the preload/JIT win —
those only pay off across many requests in a long-lived FPM worker, which a
single-shot process can't amortize. Measure on the actual server under
concurrency:

```bash
# Apache bench — 2000 requests, 50 concurrent
ab -n 2000 -c 50 http://your-host/

# or wrk — 30s, 8 threads, 100 connections
wrk -t8 -c100 -d30s http://your-host/
```

Run it before and after applying preload + `optimize` + `validate_timestamps=0`
and compare `Requests/sec`. That delta is the number that matters.

---

## What's intentionally NOT cached

- **Global middleware** (SecurityHeaders, CSRF, CORS) is re-applied fresh on
  every boot, never cached — so the security stack always reflects the
  current framework version even against a stale route cache.
- **Module `register()` / `boot()`** still run each request (they bind
  services, menu items, widgets). Only route *registration* is cached.
- **Module configs** injected via `mergeConfig()` merge at runtime; the
  config cache only pre-seeds the base `config/` files.
