# Changelog

All notable Sofy releases. Edit this file to attach a description to any
version — `/admin/system/update` parses sections starting at `## vX.Y.Z`
and shows them as release notes. Falls back to GitHub Releases when the
file is missing.

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
