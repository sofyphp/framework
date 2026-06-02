# Changelog

All notable Sofy releases. Edit this file to attach a description to any
version — `/admin/system/update` parses sections starting at `## vX.Y.Z`
and shows them as release notes. Falls back to GitHub Releases when the
file is missing.

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
