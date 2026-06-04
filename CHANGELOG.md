# Changelog

All notable Sofy releases. Edit this file to attach a description to any
version — `/admin/system/update` parses sections starting at `## vX.Y.Z`
and shows them as release notes. Falls back to GitHub Releases when the
file is missing.

## v0.8.0 — 2026-06-04

**New: a `UI::chat` component and a Messenger module — user-to-user messaging
in the admin (1:1 DMs + group channels), live.**

#### `UI::chat` (framework core)

A reusable chat thread: own/other message bubbles, composer with Enter-to-send
(Shift+Enter = newline), auto-grow textarea, auto-scroll. Transport-agnostic —
you give it a `sendUrl` (POST body → message JSON) and a `pollUrl`
(GET ?after=id → messages JSON). Live updates via polling by default;
optionally upgrades to WebSocket push. Markup-only — `sofyChat` behaviour +
styles ship once per page from Page (the DataTable/Combobox pattern).

#### `modules/Messenger`

Self-contained module (PSR-4 `Messenger\`):

  • `chat_channels` / `chat_participants` / `chat_messages` migration
    (driver-agnostic). DMs use a canonical `dm_key` so a user pair always maps
    to one channel; groups are named with N participants.
  • Models `Channel` (directBetween / createGroup / participants), `Participant`
    (membership, read cursor, unread counts), `Message` (since-id polling).
  • `MessagesController` under `/admin/messages` (behind EnsureAdmin): inbox +
    thread (renders `UI::chat`), `send`/`poll` JSON endpoints, start-direct /
    start-group. Uses the searchable `UI::combobox` to pick a user.
  • Menu item **Сообщения** with a live unread-count badge.
  • `config('messenger.ws_url')`, `poll_interval`.

#### Real-time without Redis

Sends + persistence are always HTTP (CSRF + auth). The optional WebSocket layer
carries only a *bump* signal: after a send the browser tells the channel room
to refetch, and `Messenger\WebSocket\ChatHandler` relays it to everyone in the
room — message bodies still travel over HTTP. So instant delivery needs only a
running `ws:serve` (no Redis bridge), and polling remains the always-on
fallback that auto-reconnects.

```bash
php sofy module:install Messenger && php sofy migrate
# optional realtime:
php sofy ws:serve --handler="Messenger\WebSocket\ChatHandler"
# + MESSENGER_WS_URL=wss://host/ws
```

#### Verified

  • Models on SQLite: DM dedup by `dm_key` (directBetween(7,3) reuses
    directBetween(3,7)), participants, group creation, `Message::since(after)`,
    unread counts (own messages excluded; markRead zeroes them).
  • `UI::chat` renders own/other bubbles, data-send/poll/last attrs, WS mode,
    nl2br bodies.
  • HTTP: `/admin/messages` + thread gated (302→login, not 404), `send`
    CSRF-protected (419), `sofyChat` JS + chat CSS present on every page,
    module loads (0 failed). All module files lint clean.

#### Docs

`docs/18-messenger.md` — enabling, the polling-vs-WebSocket model, the
`UI::chat` endpoint contract, and the data model.

## v0.7.0 — 2026-06-03

**Consolidation release. One tag that rolls up everything since 0.1 — deploy
this and a server on any older version comes fully current in a single
`php sofy update`, no matter which intermediate tags it did or didn't see.**

No new behaviour beyond what the entries below already describe — this exists
so the framework can be updated in one jump. Detailed per-version notes for
every change are preserved below this entry.

#### What this bundles (highlights since 0.1)

  • **Security hardening (0.4.13):** auth-by-default for /admin, global CSRF +
    SecurityHeaders + secure session cookies, CORS locked to APP_URL, zip-slip
    guards, `orderBy` allow-list, debug-page secret scrubbing,
    `Url::sameOrigin`.
  • **Built-in admin login (0.5.2) + the login saga (0.6.1–0.6.4):** a shipped
    `/admin/login` (UI-component form, CSRF, throttle); fixed `Auth::attempt`
    array-access-on-Model bug; fixed the `/admin ⇄ /admin/login`
    ERR_TOO_MANY_REDIRECTS loop; **added the missing `auth()` helper** that was
    the loop's true root cause.
  • **Performance (0.5.0–0.5.1):** wired the previously-dead route & config
    caches into boot, opcache preload generator, `php sofy optimize` /
    `optimize:clear`, and `full-install` auto-applies the production opcache
    profile (now chowning caches to the web user).
  • **Search engine (0.6.0):** zero-dependency inverted index
    (`Sofy\Search`), `Searchable` model trait, `database`/`collection` drivers,
    `search:import` / `search:flush`, and a searchable `UI::combobox` built on
    it (the Orders catalog dropdown now uses it).
  • **Products module + Orders↔Products integration**, module install/quarantine
    hardening, and the driver-aware schema/admin work from the 0.4.x line.

#### Also fixed in this release — two parse errors present since the initial 0.1 commit

Both made their class fatal to even load (caught by `php -l` across all 267
`src/` files during release prep):

  • `Sofy\Events\Dispatcher::setInstance()` declared `static $instance` —
    `static` isn't a valid parameter type. Now `self $instance`.
  • `Sofy\Console\Schedule\Event::$command` was typed `string|callable` —
    `callable` isn't a valid property type. Now `string|\Closure`.

#### Deploying

`php sofy update` overwrites `src/`, `bootstrap/` and the `sofy` CLI from the
latest release — so **all framework fixes above (which all live in `src/`)
arrive in full**. App-layer additions (`modules/Products`, `config/search.php`,
the Orders dogfood, `Main/Controllers`, migrations, docs) are not touched by
the updater — pull those with your normal app deploy if you want them; the
framework is fully functional and fixed without them.

```bash
php sofy update                 # pulls v0.7.0 = entire current framework
sudo systemctl restart php8.5-fpm
# clear the sofy_session cookie once if you were stuck in the old loop
```

## v0.6.4 — 2026-06-03

**THE redirect loop, root cause: the `auth()` helper never existed.**

v0.6.2 reasoned the loop away assuming `auth()` worked. It didn't — there was
no `auth()` helper in the framework at all. Every call site guarded it as
`function_exists('auth') ? auth()->user() : <fallback>`, and the fallbacks
disagreed:

  • `EnsureAdmin` (the gate) fell back to **`null`** → with no `auth()` helper
    it treated *everyone* as anonymous and always redirected to /admin/login.
  • `AuthController::showLogin` fell back to `Auth::user()` (correct) → an
    authenticated user there redirected to /admin.

So a logged-in user bounced forever: /admin → /admin/login → /admin → …
ERR_TOO_MANY_REDIRECTS. The login page returning 200 and v0.6.2 being deployed
were both true and both irrelevant — the gate could never see the session.

Fixes:
  • **Added the `auth()` helper** (`src/Support/helpers.php`) — returns a guard
    proxying to the `Auth` facade: `auth()->user()/check()/id()/logout()/
    attempt()`. It was referenced throughout framework code and docs but never
    defined.
  • `EnsureAdmin` now calls `Auth::user()` / `Auth::check()` / `Auth::logout()`
    **directly** — the security gate must never depend on a helper that might be
    absent, and must never silently treat everyone as anonymous.
  • `AuthController` and `AdminPage` likewise use the `Auth` facade directly
    instead of the `function_exists('auth')` ternary.

Verified over HTTP: /admin/login → 200, /admin → exactly one redirect to the
login page (num_redirects=1), no loop. `auth()` now resolves and proxies the
facade.

After deploy: `php sofy update` + restart php-fpm + clear the `sofy_session`
cookie once. Login then sticks and /admin loads.

## v0.6.3 — 2026-06-03

**Fix: `full-install` left the optimize caches root-owned, so a later
`php sofy optimize` / `optimize:clear` run as www-data died with
"Permission denied" — and the stale route cache kept serving old behaviour.**

Two fixes:

  • `FullInstallCommand::optimizeForProduction()` now `chown`s
    `bootstrap/cache` to the web user after generating caches. If the optimize
    step ran via the root fallback (no runuser/sudo), the route/config/preload
    caches were root-owned; the web user could then neither rewrite nor remove
    them, and the frozen route cache kept serving the pre-update routing table.

  • `OptimizeClearCommand` no longer fatals on a permission error (with
    APP_DEBUG on, a raw `unlink()` warning became an ErrorException and aborted
    + rendered the whole debug page). It now `@unlink`s, reports each file it
    couldn't remove, and prints the exact `sudo rm` + `chown` to fix it.

If you're stuck now (root-owned caches), the manual fix:

```bash
sudo rm -f bootstrap/cache/{routes,config,preload}.php
sudo chown -R www-data:www-data bootstrap/cache
sudo systemctl restart php8.5-fpm
```

A frozen route cache is the other reason a redirect/behaviour fix can look
"not deployed": `Application::boot()` restores cached routes and skips the
fresh build, so new routes/middleware wiring don't take effect until the cache
is cleared.

## v0.6.2 — 2026-06-03

**Hotfix: ERR_TOO_MANY_REDIRECTS on /admin/login — login page and admin gate
disagreed on what "authenticated" means.**

`AuthController::showLogin()` redirected to /admin when `Auth::check()` was true
(session has `_auth_id`), but `EnsureAdmin` decides access with `Auth::user()`
(actually loads the model). When those disagree — a dangling session whose
`_auth_id` no longer resolves to a user (deleted row, or an `_auth_id` of 0) —
you get an infinite bounce: /admin/login → /admin → /admin/login → …

Fixes:
  • `showLogin()` now uses the same test as the gate: `Auth::user() !== null`.
    Since it redirects to /admin under exactly the condition EnsureAdmin lets
    /admin through, the two can no longer disagree — the loop is impossible.
  • A stale session (check() true but user() null) is now cleared with
    `Auth::logout()` in both `showLogin()` and `EnsureAdmin` before redirecting,
    so junk sessions self-heal instead of ping-ponging.

If you hit the loop before upgrading, clear the site's cookies once after
deploying (the dangling session cookie is what was bouncing).

Verified: /admin/login → 200, /admin → one redirect to login, following
redirects settles at 200 with num_redirects=1 (no loop).

## v0.6.1 — 2026-06-03

**Hotfix: admin login always failed with "Login is temporarily unavailable" —
`Auth::attempt()` used array access on a Model object.**

`Builder::first()` returns a hydrated Model, but `Auth::attempt()` read
`$user['password']` and `$user['id']` — array access on an object that doesn't
implement ArrayAccess. PHP throws `Error: Cannot use object of type … as
array` on every match. `??` doesn't suppress it (that's a fatal, not an
undefined-key notice). The v0.5.2 AuthController caught the throwable and
rendered a generic 503 "check the database connection" — which is why a freshly
`admin:create`d account still couldn't sign in.

The bug predates the login form: nothing called `attempt()` until v0.5.2
shipped one, so it never surfaced.

Fixed `Auth::attempt()` to use object access (`$user->getAttribute('password')`,
`$user->getPrimaryKeyValue()`), with an array fallback for callers that pass a
raw row. Verified end-to-end against SQLite: correct password authenticates,
wrong password / unknown user return false cleanly, no exception on any path.

Also: AuthController now `error_log()`s the real exception (class + message +
file:line) instead of swallowing it behind the generic message — so the next
genuine DB problem is diagnosable from the server log.

Getting in after upgrade: nothing extra — `php sofy admin:create` then sign in.

## v0.6.0 — 2026-06-03

**New subsystem: a zero-dependency search engine — inverted index + in-memory
ranker — and the first component built on it, a searchable Combobox.**

This started from "are there enough components?" The honest gap wasn't count
(46 components) but depth at the CRUD layer: building Orders↔Products we
hand-wrote a product `<select>` that dies past a few hundred rows. Rather than
bolt search onto one component, this adds a real engine that any component (and
any model) can consume.

#### `Sofy\Search` — the engine

  • `Search` facade — `Search::query(Product::class, 'red router')->get()`,
    `Search::index($model)`, `Search::import(Model::class)`, `Search::flush()`,
    `Search::rank($items, $q, $textOf)` (in-memory, for components).
  • `Engine` — tokenizes + weights documents, ranks queries. BM25-lite:
    score = Σ (field weight × term frequency) over matching terms; the last
    query token matches as a prefix (autocomplete).
  • `Tokenizer` — lowercase, accent-fold (Latin-1 + Cyrillic, no ext-intl),
    stopwords (en/ru/custom), min length, prefix. Config in
    `config/search.php`.
  • Drivers (mirrors the Cache/Session/Grammar driver pattern):
      - `DatabaseDriver` — one portable `search_index` table; identical on
        MySQL / PostgreSQL / SQLite, no engine-specific full-text DDL.
      - `CollectionDriver` — ephemeral in-memory index (tests, transient).
  • `Searchable` trait — `use Searchable;` auto-(re)indexes a model on save
    and drops it on delete (via `SearchableObserver`), adds `Model::search()`.
    Declare field weights in `config('search.indexes')`.
  • `SearchResult` — iterable, countable; hydrates models in ranked order.

Migration `create_search_index_table` (driver-agnostic). Commands:
`php sofy search:import "App\Models\Post" [--fresh]`, `php sofy search:flush`.

#### `UI::combobox` — searchable select (first consumer)

The component a plain `<select>` can't be once you pass a few hundred options.

```php
UI::form(...)->combobox('Product', 'product_id', $options, selected: $id);
UI::combobox('product_id', $options);                       // standalone
UI::combobox('product_id', [])->endpoint('/products/search'); // Search-backed
```

  • Local mode: filters provided options client-side (accent-aware), to a few
    thousand rows.
  • Remote mode: `->endpoint($url)` fetches `?q=…` as you type — back it with a
    3-line route calling `Model::search()`. The engine ranks; no glue.
  • Markup-only component; the `sofyCombo` behaviour + styles live in Page
    (same pattern as DataTable's `sofyDT`), shipped once per page. Keyboard
    nav (↑/↓/Enter/Esc), click-outside close, hidden value field.
  • New `Form::combobox()` field kind; `UI::combobox()` facade.

#### Dogfood

`modules/Orders` — the catalog "add item" dropdown that was a hand-built
500-option `<select>` is now `UI::combobox(...)->placeholder('Поиск товара…')`.
The exact hand-rolled HTML that prompted this is gone.

#### Verified

  • Tokenizer: stopwords dropped, accents folded, term frequencies
  • Engine (CollectionDriver): field-weighted ranking (name w3 > desc w1),
    multi-term, prefix autocomplete, `rank()` for components
  • DatabaseDriver on SQLite: put / weighted search / prefix / LIKE-escape
    (`%` → 0 results) / remove
  • Combobox: hidden value + selected label + options render; in a UI::form
    with label wrapper; `sofyCombo` JS + CSS present on every Page; /ui-demo
    still 200

#### Notes

No stemming (prefix matching covers autocomplete; add synonyms in
`toSearchableArray()`). Native full-text (FULLTEXT/tsvector/FTS5) deliberately
not used — the portable table wins on identical cross-engine behaviour; a
native driver can drop in later without changing calling code.

## v0.5.2 — 2026-06-03

**Ships a built-in admin login. Closes the gap v0.4.13 opened: auth was on
by default but the framework had no login form, so a fresh deploy hit a 503
wall at /admin with no way in.**

#### `Sofy\Admin\Controllers\AuthController`

A self-contained admin login — no app view file required:

  • `GET  /admin/login`  — renders a styled login page (brand-matched,
    standalone HTML). Redirects to /admin if already signed in.
  • `POST /admin/login`  — verifies via `Auth::attempt()` against
    `Main\Models\User`, enforces the panel's `requiredRole` (admin), then
    redirects to the validated `?next=` (via `Url::sameOrigin`) or /admin.
  • `POST /admin/logout` — `Auth::logout()` + redirect to login.

Protected by the existing global CsrfMiddleware (the form embeds
`csrf_field()`). Brute-force throttle: 5 attempts per IP+email per 15 min
via the cache (fails open if cache is down). DB errors during attempt return
a clean 503, not a 500 stack.

Routes are registered in `admin-routes.php` OUTSIDE the EnsureAdmin group.
Override by defining `/admin/login` in `routes/web.php` (loaded earlier).

#### Fix: EnsureAdmin login-route detection was broken

`EnsureAdmin::loginRouteExists()` (added in v0.4.13) iterated
`Router::getRoutes()` as if it were a method-keyed map, but getRoutes()
returns a FLAT list of `['method'=>, 'route'=>]` entries — so it always
returned false. Harmless while no login route existed (the 503 hint was
correct anyway), but it meant /admin would 503 forever even after a login
route was added. Now reads the flat shape correctly, so /admin redirects to
/admin/login (302) once the route exists.

#### Logout in the admin chrome

The sidebar now shows a CSRF-protected "Sign out" button (pinned to the
bottom) when auth is enabled and a user is signed in.

#### Getting in after upgrade

```
php sofy admin:create          # seed an admin user (prompts for email/pass)
# visit /admin → redirected to /admin/login → sign in
```

#### Smoke (php -S)

  • GET /admin/login → 200, form has email/password/_token
  • GET /admin → 302 → /admin/login?next=/admin
  • GET /admin/database/sql → 302 → login (no longer publicly reachable)
  • POST /admin/login without CSRF → 419
  • POST /admin/login with token + bad creds → 401 (503 when DB unreachable)

## v0.5.1 — 2026-06-03

**`full-install` now sets up the v0.5.0 production performance stack
automatically.**

The opcache preload + JIT config and the route/config caches were a manual
post-install step. The installer now does it for you as the final step (after
migrations, so caches reflect the fully-installed app).

New wizard prompt — *"Optimize for production (opcache preload + JIT +
route/config cache)?"* (default yes). When enabled, the new
`optimizeForProduction()` step:

  1. `composer dump-autoload --optimize --classmap-authoritative --no-dev`
  2. `php sofy optimize` (run as the web user so cache files are owned by
     FPM's user, not root) → route cache + config cache + preload.php
  3. Writes `/etc/php/{ver}/fpm/conf.d/99-sofy.ini` (falls back to
     `/etc/php.d/` on RHEL) with:

         opcache.enable=1
         opcache.memory_consumption=256
         opcache.max_accelerated_files=20000
         opcache.preload={app}/bootstrap/cache/preload.php
         opcache.preload_user=www-data
         opcache.validate_timestamps=0
         opcache.jit=tracing
         opcache.jit_buffer_size=64M

  4. `systemctl restart php{ver}-fpm` (restart, not reload — preload only
     engages on a full restart)

Order matters and is enforced: preload.php is generated (step 2) BEFORE FPM
restarts pointing `opcache.preload` at it (step 4), so FPM never logs a fatal
missing-preload error on startup.

The install summary box gained an *Optimize* row. The step prints the
post-deploy reminder: re-run `php sofy optimize` + restart FPM after every
deploy (required because `validate_timestamps=0` makes opcache ignore file
mtimes).

## v0.5.0 — 2026-06-03

**Performance release. Wires three dead-on-arrival cache commands into the
boot path, adds opcache preloading, and makes the default app cacheable.**

Baseline measured first: boot is already ~0.6–1 ms warm, TTFB ~1.2 ms.
Single-request latency has almost nothing left to cut. The wins here are in
**throughput** — stop per-request work that never changes between requests —
and they show up under FPM concurrency, not in a single-shot CLI loop.

#### `php sofy optimize` — one command for production

New command runs config:cache + route:cache + generates an opcache preload
script, then prints the php.ini block to wire it. `php sofy optimize:clear`
reverses everything for local dev.

#### Route cache — the dead feature, now alive

`php sofy route:cache` existed since the first release but wrote a file
`Application::boot()` never read (`grep -c cache/routes Application.php` was
literally 0). Now:

  • `Router::cacheState()` / `restoreState()` snapshot the FULL routing
    table — dynamic map, the O(1) static index, AND named routes — including
    each Route's pre-compiled URL regex (the per-request cost we skip).
  • `Application::boot()` restores from `bootstrap/cache/routes.php` when
    present and skips requiring every routes.php + reconstructing every Route.
    Cost saved scales `O(number of routes)`.
  • Global middleware is deliberately NOT cached — re-applied fresh each boot
    so the security stack always matches the current framework version.

Closures can't serialize, so `route:cache` lists any closure routes and
refuses (one closure disables the whole all-or-nothing cache).
`Router::uncacheableRoutes()` powers a non-throwing check so `optimize`
soft-skips route caching while still applying config + preload.

#### Config cache — also was dead, also wired

`php sofy config:cache` pre-merges every `config/*.php` (with `env()`
resolved) into one file. `Application::loadCachedConfig()` now seeds the
config cache from it at construct — zero per-file `require`/`stat` per
request. Re-run after editing `.env` (values are baked in at cache time).

#### opcache preload generator

`optimize` writes `bootstrap/cache/preload.php` — a self-locating script
(`dirname(__DIR__, 2)`) that `opcache_compile_file`s every class under
`src/`, `app/`, `Main/`, `modules/`, `config/`. Point `opcache.preload` at
it and FPM compiles the whole framework into shared memory ONCE at master
start; workers inherit ready classes with zero per-request compile. Typical
real-world win: 1.3–2× throughput. Regenerate + reload FPM per deploy.

#### Default app is now cacheable

The shipped scaffold used closure routes, which blocked route caching out of
the box. Converted to controllers:

  • `routes/web.php` welcome / debug-error / lang-switch → new
    `Main\Controllers\HomeController` (lang switch now uses `Url::sameOrigin`)
  • `routes/api.php` ping → new `Main\Controllers\Api\PingController`
  • Demo module → new `Demo\Controllers\DemoController`

A fresh app now caches all 40 routes cleanly.

#### New APIs

  • `Router::cacheState(): array` / `restoreState(array): void`
  • `Router::uncacheableRoutes(): list<string>`
  • `Application::buildRoutes(): void` (fresh, un-cached route build)
  • Commands: `optimize`, `optimize:clear`

#### Docs

`docs/16-performance.md` — the three levers (preload, validate_timestamps=0,
route/config cache), the php.ini block, why CLI benchmarks can't show the
preload win, and how to measure the real delta with `ab`/`wrk` on the server.

#### Functional verification (php -S, serving from cache)

  • All routes serve from cache: /, /docs, /docs/{section}, /ui-demo, /demo,
    /api/ping, /api/demo → 200; /admin → 503 (auth)
  • Security headers still present (global mw fresh, not cached)
  • CSRF still 419 on POST without token
  • Config served from cache (app.name correct)
  • Route cache build: 40 routes → bootstrap/cache/routes.php; warm-boot
    delta ~13% (0.576 → 0.501 ms), scales with route count

> Note: the preload + JIT + `validate_timestamps=0` wins are FPM-under-load
> effects and cannot be shown in a single-shot CLI benchmark — measure on the
> server with `ab -n 2000 -c 50` before/after. See docs/16-performance.md.

## v0.4.13 — 2026-06-02

**Security hardening release. Fixes 10 findings from the v0.4.12 audit
+ verified live against http://5.42.121.9.**

#### Auth-by-default for /admin (was the catastrophic one)

`Sofy\Admin\AdminPanel::$requireAuth` flips from `false` to `true`. The
live probe against 5.42.121.9 confirmed the worst case: `/admin/database`
and `/admin/database/sql` were serving HTTP 200 to the public internet.
A raw SQL console gated only by the absence of a login route a dev
forgot to wire is not a default.

Migration: a fresh install with no login form now shows a 503 setup
hint at `/admin/*` explaining the three ways forward (wire login,
disable framework auth, change loginUrl). The 503 page is self-
contained HTML — no styles depend on the admin chrome.

If you've already gated /admin upstream (nginx auth_basic, VPN,
reverse-proxy ACL) and don't want the framework's gate, add
`\Sofy\Admin\Admin::panel()->requireAuth = false;` to bootstrap/app.php.

#### CSRF middleware actually runs now

`CsrfMiddleware` existed since the framework's first release but was
never wired into any default middleware stack — every state-changing
POST in /admin accepted requests from any Origin. The audit caught it,
the live probe verified it (`POST /admin/system/marketplace/refresh`
returned 302, not 419). Now `Sofy\Http\Middleware\CsrfMiddleware` is in
the global stack via `Router::globalMiddleware()`, applied to every
non-GET/HEAD/OPTIONS request except `/api/*` (Bearer-token APIs don't
need it). Forms that already include `_token` keep working unchanged.

#### `Sofy\Http\Middleware\SecurityHeaders`

New middleware, wired globally. Sends:

  X-Content-Type-Options: nosniff
  X-Frame-Options: DENY
  Referrer-Policy: strict-origin-when-cross-origin
  Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()
  Content-Security-Policy: …self-origin-only with 'unsafe-inline' for
                            style + script because UI components inline
                            both. Tighten in your own middleware once
                            you've migrated handlers to addEventListener.
  Strict-Transport-Security: max-age=2y; includeSubDomains  (HTTPS only)

Idempotent — never overrides a header you already set per-route.

#### `Session::start()` cookie params

`session_set_cookie_params()` now runs before `session_start()` with
`HttpOnly: true`, `SameSite: Lax`, and `Secure: true-when-https`.
Previously sessions inherited php.ini defaults, which on most distro
installs left HttpOnly off. JS-XSS-into-document.cookie attacks are
now neutered.

#### `Router::globalMiddleware()` API

```
$router->globalMiddleware([
    \Sofy\Http\Middleware\SecurityHeaders::class,
    \Sofy\Http\Middleware\CsrfMiddleware::class,
]);
```

Applied via `Application::bootHttpMiddleware()` which runs from
`Application::boot()` before `loadRoutes()`. Override with
`Application::$autoSecurityMiddleware = false` before boot if you want
to wire your own stack from scratch.

`Request::isHttps()` added — checks `HTTPS`, `SERVER_PORT=443`, and
`X-Forwarded-Proto: https` so it works behind a TLS-terminating
reverse proxy.

`Response::hasHeader()` added for SecurityHeaders to check
"is this header already set?" without overwriting.

#### CORS default

`config/cors.php` now defaults `allowed_origins` to `[APP_URL]` instead
of `['*']`. Override via `CORS_ALLOWED_ORIGINS=https://app.example.com,
https://staging.example.com` in `.env`. The wildcard combined with
public /admin was the audit's third critical finding.

#### `Sofy\Support\Url::sameOrigin()`

New helper for safe post-login redirects. Returns `$candidate` if it's
a local path or same-origin URL, otherwise `$fallback`. Defends against
classic open-redirect via `?next=https://attacker.com`.

```php
return Response::redirect(Url::sameOrigin($request->input('next'), '/admin'));
```

Eight unit cases verified (null, empty, `/path`, `//evil`, scheme
mismatch, host mismatch, same-origin absolute, `javascript:`).

#### Zip-slip defense-in-depth

`Sofy\Module\Marketplace\Installer::extractZip()` and
`Sofy\Console\Commands\UpdateCommand` now iterate archive entries
BEFORE calling `ZipArchive::extractTo()` and reject anything with
`..` segments, leading `/`, or `C:\` style absolute paths. PHP ≥7.4
has built-in zip-slip protection but it's not watertight across
symlink + Windows-path edges; this closes the gap.

#### `Builder::orderBy()` direction allow-list

`Sofy\Database\Builder::orderBy()` now rejects any direction that
isn't exactly `ASC` or `DESC` (case-insensitive, trimmed) with an
`InvalidArgumentException`. Catches `?sort=ASC; DROP TABLE users`
patterns when devs forward user input straight into orderBy.

#### Debug page scrub-list

`Sofy\Core\ExceptionHandler::requestHtml()` now redacts request fields
whose key contains `password`, `passwd`, `pwd`, `secret`, `token`,
`_token`, `authorization`, `auth`, `api_key`, `apikey`, `cookie`,
`remember_token`, `session`, `sessid`, `card`, `cvv`, or `cvc`. The
debug page can be screenshotted into a bug tracker without leaking
the user's password from a failed login.

#### Live verification against 5.42.121.9 (pre-fix)

  /admin                    → HTTP 200  (no auth)
  /admin/users              → HTTP 200
  /admin/database           → HTTP 200
  /admin/database/sql       → HTTP 200  ← raw SQL console publicly executable
  /admin/system             → HTTP 200
  /admin/system/marketplace → HTTP 200
  /admin/system/modules     → HTTP 200
  X-Frame-Options           → MISSING
  X-Content-Type-Options    → MISSING
  Content-Security-Policy   → MISSING
  Referrer-Policy           → MISSING
  Strict-Transport-Security → MISSING

After applying this release + `Admin::useAuth('admin')` in
bootstrap/app.php + a login form, all of the above invert.

#### Smoke (php -S, 6/6 pass)

  [1] All 5 security headers present on GET /
  [2] POST /admin/database/sql without _token → 419
  [3] /admin without login route → 503 setup hint
  [4] Set-Cookie includes HttpOnly + SameSite=Lax
  [5] No CORS header on cross-origin GET (browser blocks)
  [6] Url::sameOrigin — 8 cases, all expected

## v0.4.12 — 2026-06-02

**Hotfix: typed Model closures in OrdersController + ProductsController
crashed `UI::dataTable` / `UI::table` rendering.**

Both `UI::dataTable` and `UI::table` normalise rows to associative
arrays via `toArray()` before invoking column closures — see
`DataTable::normalizeRow()` / `Table::normalizeRow()`. The Orders and
Products controllers shipped with closures typed `fn(Order $o)`,
`fn(OrderItem $i)`, `fn(Product $p)` and dereferenced `$o->id`, which
worked at lint time and at runtime ONLY when the table was empty
(closures never invoked). The first real row produced:

    TypeError: Argument #1 ($p) must be of type
    Products\Models\Product, array given,
    called in src/View/UI/DataTable.php:100

Fixed by switching every typed-closure column to `fn(array $r)` with
`$r['field']` lookups — same pattern the framework's own
`UsersController` uses. Affects the listing page closures in both
controllers and the OrderItem table inside the order detail page.

No framework changes — pure module-side fix.

## v0.4.11 — 2026-06-02

**Fix: uninstalled modules no longer surface as `Failed`. Plus a Products
module and Orders ↔ Products integration.**

#### Loader: pre-flight catches PSR-4 misses BEFORE register() runs

v0.4.10 introduced the enable-list, but the first-boot rule was too
permissive — it auto-enabled every module folder on disk, regardless
of whether the module's PSR-4 was registered in `composer.json`. A
user upgrading from v0.4.6 with an already-copied `Orders/` folder
ended up with Orders in the registry but no PSR-4 entry, so the next
boot loaded `Orders\Orders` (via `require_once`), called its
`register()`, which referenced `Orders\Admin\Widgets\OrdersTodayWidget`,
which composer couldn't autoload — Failed. The user thought they
hadn't installed Orders, the framework thought they had.

Two changes:

  • **First-boot auto-enable filters by composer.json psr-4.** Only
    folders whose `Name\` namespace is mapped in
    `autoload.psr-4` get auto-enabled. Folders without PSR-4 (i.e.
    not properly installed) are left in the discoverable pile until
    `php sofy module:install {Name}` patches autoload.

  • **Pre-flight check on every enabled module.** Before requiring the
    module file, the loader peeks at composer.json to verify the
    namespace exists. If not, the module is moved to a new
    `uninstalled` bucket — NOT `failed` — and `register()` is never
    called. `/admin/system/modules` shows it in a dedicated
    "Enabled but not properly installed" section with a copy-pasteable
    install command.

New ModuleLoader API: `uninstalled(): list<string>`.

#### Products module

`modules/Products/` — каталог товаров. Self-contained as Orders v0.4.7:

  • `Models/Product` — sku, name, price, stock, description, active flag,
    `Product::generateSku()` helper that yields `SKU-000001`-style ids
  • `Migrations/2026_06_02_000003_create_products_table.php`
  • `Controllers/Admin/ProductsController` — full CRUD + status filter
    + search by SKU/name
  • `Admin/Widgets/ProductsCountWidget` — active vs total tile
  • `routes.php` — eight endpoints under `/admin/products` behind
    EnsureAdmin
  • `config.php` — currency, page size, SKU prefix
  • `Orders.php::register()` adds menu entry under `Каталог` section
    with a live "active products" badge

#### Orders ↔ Products integration (soft dependency)

  • New migration `2026_06_02_000004_add_product_id_to_order_items.php`
    in `modules/Orders/Migrations/` adds a nullable `product_id` column
    to `order_items`. Idempotent: re-running checks the column first.
    No FK so deleting a Product doesn't cascade-delete history.

  • `OrderItem::product()` resolves the linked Product — but only if
    `class_exists(\Products\Models\Product::class)`. Orders has no
    hard dependency on Products being installed; the catalogue
    dropdown just disappears.

  • `OrdersController::show()` renders TWO add-item forms when Products
    is loaded: free-form (existing) + a "from catalog" select with
    active products. New `_action=add_from_catalog` handler snapshots
    name + price from the product and stores `product_id` on the item.

Catalog: `docs/marketplace.json` gets a Products entry next to Orders.

## v0.4.10 — 2026-06-02

**Modules now require explicit install — dropping a folder no longer
breaks the framework.**

User complaint after v0.4.7 was that copying a module folder onto a
server without first running `composer dump-autoload` produced a
"Class Orders\Admin\Widgets\OrdersTodayWidget not found" fatal during
boot — the framework would crash before ANY page could render. Two
parallel fixes:

  • **Enable-list at `bootstrap/modules.php`.** ModuleLoader now reads
    a registry of explicitly-enabled modules. Folders present under
    `modules/` but NOT in the registry are ignored entirely — their
    code never gets touched. `php sofy module:install {Name}` and the
    marketplace install button add to the registry as the last step of
    a successful install, so a fully-wired module appears on the next
    boot. The marketplace uninstall removes from the registry first.

  • **Defensive register() / routes() / boot() loops.** Even when a
    module IS in the enable-list, if its class can't autoload or its
    register hook throws, the loader catches the exception, drops the
    module from the active set, records it in `$failed`, and keeps
    booting. The rest of the framework stays online — broken module is
    quarantined, not contagious.

Backward compat: first boot after upgrade auto-creates the registry
with every module folder currently on disk, so existing installations
don't lose their modules. From the second boot on, newly-dropped
folders need explicit install. `bootstrap/modules.php` is gitignored
(per-install state, like .env).

New ModuleLoader API surface:
  • `enable(string $name): bool` / `disable(string $name): bool`
  • `isEnabled(string $name): bool`
  • `discoverable(): list<string>` — every folder under modules/
  • `disabled(): list<string>`     — discoverable but not enabled
  • `failed(): array<string, Throwable>` — load/register/routes/boot
  • `registryPath(): string`       — for log messages and admin UI

`/admin/system/modules` page reworked: three cards — Loaded
(unchanged), Discovered-but-not-enabled (with copy-pasteable
`php sofy module:install {Name}` command), and Failed (module name,
error message, file:line). When anything failed, a danger banner sits
at the top of the page so the operator notices.

## v0.4.9 — 2026-06-02

**Fix: APP_DEBUG=true now actually shows the debug page for boot-time errors.**

The framework's try/catch lived only inside `Application::handle()`, so a
fatal during `bootstrap/app.php` — like dropping a module folder without
running `composer dump-autoload` so its widget classes don't autoload —
escaped to PHP's default error handler. Operators saw a blank Nginx 500
instead of the rich debug page, even with `APP_DEBUG=true`.

Three-layer global capture installed in `Application::__construct`:

- `set_error_handler`  — converts PHP notices/warnings to `ErrorException`
  so they hit the same renderer.
- `set_exception_handler` — catches any throwable that escapes the
  per-request try/catch: bootstrap crashes, `Module::register()` failures,
  any wiring done before `$app->run()`.
- `register_shutdown_function` — handles fatal `E_ERROR`, `E_PARSE`,
  `E_CORE_ERROR`, `E_COMPILE_ERROR` that userland normally can't intercept.

Each path clears any partially-streamed output buffers, then routes
through `renderException()` so debug-page vs. static 500-view vs. custom
`$app->error(500, ...)` handler all behave identically regardless of
where the error originated.

A fallback emitter inside the global handler guards against the renderer
itself crashing — last-ditch plain-text response with the original
exception + the renderer-failure exception when `APP_DEBUG=true`,
otherwise just `Internal Server Error`. The browser never sees nothing.

Drive-by: the debug page's header brand said `Lu<span>ne</span>` —
leftover from the framework's previous name. Fixed to `So<span>fy</span>`
matching the rest of the rebrand (REPL banner v0.4.4).

## v0.4.8 — 2026-06-02

**Module marketplace (MVP).**

`/admin/system/marketplace` — browse, install and uninstall Sofy modules
from a central catalog without touching the shell.

  • `sofy-module.json` spec — manifest each module ships at its repo
    root (slug, name, namespace, version, requires, dist, screenshots,
    categories). Spec doc at `docs/sofy-module-spec.md`. Reference
    example at `modules/Orders/sofy-module.json`.
  • `Sofy\Module\Marketplace\Catalog` — fetches the remote catalog from
    `config('marketplace.catalog_url')` (default
    https://raw.githubusercontent.com/sofyphp/marketplace/main/modules.json),
    cached 1 h. Falls back to the bundled `docs/marketplace.json` when
    remote is unreachable, and merges in manifests detected on disk
    under `modules/` so installed modules always appear (annotated with
    `installed=true`). Legacy modules without a manifest get a
    synthesised stub so they still surface in the UI.
  • `Sofy\Module\Marketplace\Installer` — download → extract → copy to
    `modules/{Name}/` → patch `composer.json` psr-4 → composer
    dump-autoload → optional migrate. Returns `InstallResult` so
    controllers and CLI render the same outcome without try/catch.
    Pre-flight on `modules/` + `composer.json` writability mirrors the
    UpdateController discipline; resolves `php` / `composer` / `unzip`
    via `findBinary()` so it works under PHP-FPM with sparse `$PATH`.
    Dist types supported: `github-release` (latest release zip),
    `github-tag` (specific tag), `zip` (direct URL).
  • `MarketplaceController` — grid of cards with category chips +
    search; install/uninstall confirms in JS; output rendered in a
    dark `<pre>` with the operation log. Sidebar entry under System
    with the `shopping-bag` icon.
  • CLI symmetry:
        php sofy marketplace:list
        php sofy marketplace:list --installed --search=orders
        php sofy marketplace:install <slug>
        php sofy marketplace:install <slug> --no-migrate
        php sofy marketplace:uninstall <slug>

Bundled catalog ships with one entry (Orders v1.0.0 pointing at the
framework's own modules/Orders subdirectory of tag v0.4.7) so the
marketplace UI is non-empty even before sofyphp/marketplace exists.

## v0.4.7 — 2026-06-02

**Bundled Orders module — full-feature reference module.**

Drop-in `modules/Orders/` showing how to ship a complete feature in
isolation — everything the module needs (model, migrations, routes,
controller, admin menu entry, dashboard widgets, scoped CSS) lives
under its directory; nothing leaks into the host app.

  • Models — `Orders\Models\Order` (number, customer name/email, status,
    total, currency, notes) with a `hasMany` items relation and a
    `generateNumber()` helper that yields ORD-000001-style sequential
    ids. `Orders\Models\OrderItem` for line items.
  • Migrations — two auto-discovered files under `Migrations/` create
    the `orders` and `order_items` tables; driver-agnostic via the
    Schema Grammar layer (MySQL / PostgreSQL / SQLite).
  • Routes — `routes.php` registers eight endpoints under /admin/orders
    behind EnsureAdmin: index (with status filter + search), create,
    store, show (with inline line-item add/delete), edit, update,
    status quick-change, destroy.
  • Admin menu — `Orders.php::register()` adds a 'Заказы' entry in a
    'Каталог' section with a live pending-orders badge counter, plus
    two dashboard widgets:
      - OrdersTodayWidget — count today vs yesterday
      - OrdersRevenueWidget — sum of total across paid/shipped/completed
  • Configuration — `config.php` ships statuses (pending → paid →
    shipped → completed / cancelled), per-status alert variant mapping,
    default currency, page size, and number prefix.
  • Graceful degradation — all touchpoints (widgets, controller, menu
    badge) catch DB exceptions so /admin keeps loading even before
    `php sofy migrate` has been run.

To install in a host app: drop `modules/Orders/` in place and add
`"Orders\\\\": "modules/Orders/"` to composer.json's psr-4 (already done
here). The module loader picks it up on the next boot.

## v0.4.6 — 2026-06-02

**Fix: one-click update from `/admin/system/update` actually works.**

The previous version's "Update now" button often failed silently or with
a confusing tail of CLI noise because the controller leaned on a
fragile `shell_exec('echo y | php sofy update …')` pipeline. Three
classes of failures fixed:

- **Wrong `php` binary.** `PHP_BINARY` under PHP-FPM is `php-fpm`, not
  the CLI. `UpdateController` now resolves `php` via a `findBinary()`
  helper that walks `/usr/local/bin`, `/usr/bin`, `/bin`,
  `/opt/homebrew/bin`, `/opt/local/bin`, then the web user's `$PATH`,
  and only falls back to `PHP_BINARY` when its basename actually looks
  like a CLI (`php` / `php8.x`).
- **`composer` missing on FPM `$PATH`.** Previously a silent half-
  update — composer dump-autoload would just not run, leaving new
  classes unloadable. Now composer is invoked from the controller with
  the same `findBinary()` resolver; if it isn't found, the update still
  succeeds and the user gets a clear warning telling them to run
  `composer dump-autoload -o` from a shell.
- **Fragile confirmation piping.** `UpdateCommand` now takes a
  `--no-interaction` flag and the controller passes it; the browser
  already showed a JS confirm so the CLI doesn't ask again. Also added
  `--no-composer` so the controller can drive composer itself.

Process model rewritten: `shell_exec` → `proc_open` with explicit
`cwd = $basePath`, extended `$PATH` env, hard 300s timeout (kills with
SIGKILL on overrun), non-blocking stdout+stderr capture.

Pre-flight added: before launching the subprocess, the controller
verifies `is_writable()` on `src/`, `bootstrap/`, `sofy`, `composer.json`.
Any failure renders an "Update did not complete" danger page with the
list of unwritable paths and the web user's name (via `posix_getpwuid`),
so the fix is obvious. Same page is used for missing-binary and
non-zero exit code outcomes.

Docs: `README.md`, `docs/01-getting-started.md` and `docs/12-console.md`
now cover the `sudo php sofy full-install` server provisioning wizard
(6 steps: domain · PHP 8.3/8.4/8.5 · Caddy/Nginx/Apache · SSL via
Caddy auto or Certbot · MySQL/PostgreSQL/SQLite · cron + Supervisor +
migrations) and its `--no-interaction` mode, plus the
`/admin/system/update` web flow and the `php sofy update` CLI options.

## v0.4.5 — 2026-06-02

**Bug fix: `UI::alert()` rendered HTML tags as literal text.**

`UI::alert($message)` used to escape its message and title via
`htmlspecialchars`, so calling it with `'<code>src/</code>'` produced
`&lt;code&gt;src/&lt;/code&gt;` on the page. Same bug class as the
v0.4.1 database table fix, on a different component.

Both `$message` and `$title` now accept `string|Component`. Plain
strings keep getting escaped (the safe default for user-typed text);
pass `UI::raw('<code>…</code>')` when you need inline markup. Mirrors
how `UI::card($content)` already worked.

Visible on `/admin/system/update` — the "Heads up" warning now shows
`<code>` chips for `src/`, `bootstrap/`, `sofy` and `php sofy update`
instead of escaped angle brackets.

## v0.4.4 — 2026-06-02

**Admin: one-click framework updates + release notes feed.**

New page at `/admin/system/update`:

- Status banner — Up to date / Update available / Offline check
- Four stat tiles: Installed / Latest / Releases / PHP
- "Update now" button that runs `php sofy update --no-migrate` on the
  server, captures its output and shows it in a dark `<pre>`
- Release notes pulled from GitHub Releases at `sofyphp/framework` with
  a 30-min cache; falls back to a local `CHANGELOG.md` when no GitHub
  Releases exist (or the API is unreachable)
- Each release card shows an `installed` / `newer` / `older` badge and
  the installed card lights up with an accent border
- "Refresh release notes" button busts the cache on demand
- Mini-markdown renderer handles headings, lists, bold/italic, inline
  + fenced code, http(s) links

Drive-by: REPL banner used to read "Lune REPL" — leftover from the
framework's previous name. Fixed to "Sofy REPL" to match the brand
pattern used everywhere else.

## v0.4.3 — 2026-06-01

**Default dashboard widget pack.**

A fresh install now lands on a populated `/admin` instead of an empty
state. Seven stock widgets ship with the framework and are auto-registered:

- `WelcomeWidget` — full-width hero with version + clock
- `UsersCountWidget` — total users + last-7-days delta
- `DatabaseStatsWidget` — table count + driver chip
- `ModulesCountWidget` — auto-discovered module count
- `PhpRuntimeWidget` — PHP version + SAPI + peak memory
- `QuickActionsWidget` — icon-tile grid (Users / Database / SQL / Modules / System)
- `SystemHealthWidget` — KV pane with runtime summary

`DashboardController` now respects `AdminWidget::$cols` (1 / 2 / 4) and
packs consecutive same-size widgets into a single grid row.

## v0.4.2 — 2026-06-01

**Framework-wide `Sofy\View\Icons` library + `UI::icon()` factory.**

The admin sidebar's icon catalog graduated into a general-purpose
catalog of 107 Feather-style SVGs that any component can reuse.

- New: `UI::icon('home', size: 20, color: 'var(--accent)')`
- New: `Sofy\View\UI\Icon` component with size / color / stroke-width controls
- `Sofy\Admin\Icons` kept as a thin alias — old imports keep working
- Icons inherit text color via `stroke="currentColor"` so they fit any theme

## v0.4.1 — 2026-05-31

**Bug-fix release.**

- Fixed database table list rendering links as escaped HTML text
- Fixed `UI::card(...)` 3-arg call site
- Introduced the original 12-icon SVG set for the admin sidebar

## v0.4.0 — 2026-05-30

**Admin: System info, Database browser, SQL console.**

- `/admin/system` — APP/PHP/OS/extensions cards
- `/admin/system/modules` — auto-discovered modules list
- `/admin/database` — table list with row counts
- `/admin/database/table/{name}` — columns + first 100 rows
- `/admin/database/sql` — raw SQL console with read/write detection
- `ModuleLoader::modules()` made public so widgets can introspect the registry

## v0.3.1 — 2026-05-29

**Admin auth + EnsureAdmin middleware.**

- `Admin::useAuth()` toggles authentication enforcement
- `EnsureAdmin` middleware redirects unauthenticated requests to `/admin/login`
- Per-route `requiredRole` check when the User model uses `HasRoles`

## v0.3.0 — 2026-05-29

**CMS-style admin panel.**

- `Sofy\Admin\Admin` facade + `AdminPanel` singleton
- `MenuItem` DTO with section / order / icon / `visibleIf`
- `AdminWidget` abstract for dashboard cards
- `AdminPage` chrome renderer (sidebar + topbar + content)
- Module extensibility: `Admin::menu()->add(...)` + `Admin::widget(...)`

## v0.2.0 — 2026-05-28

**Driver-aware Schema layer.**

- `Sofy\Database\Schema\Grammar` abstract
- Concrete `MySqlGrammar` / `PgSqlGrammar` / `SqliteGrammar`
- Schema migrations now work identically across all three databases
- Connection-aware `Grammar::forConnection($conn)` factory
