<div align="center">

# So<span>fy</span>

### The batteries-included PHP framework with **zero runtime dependencies**

Build the whole app — UI, admin, auth, search, real-time chat, notifications —
with nothing but PHP. No node, no templates, no build step, no vendor bloat.

[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-22c55e)](LICENSE)
[![Dependencies](https://img.shields.io/badge/runtime%20deps-0-ff6b3d)](composer.json)
[![Tests](https://img.shields.io/badge/tests-PHPUnit-3b82f6)](tests)

**English** · [Русский](README.ru.md) · [Docs](docs/) · live UI demo at `/ui-demo`

</div>

---

## Why Sofy?

Most frameworks hand you a router and a stack of Composer packages, then send you
to npm for the UI. **Sofy ships the whole product** — and renders styled HTML
straight from PHP.

```php
return UI::page('Dashboard')
    ->add(
        UI::grid(3, [
            UI::stat('Revenue', '$42k')->color('#10b981'),
            UI::stat('Users',    1240, '+5%'),
            UI::stat('Active',    983, '+2%'),
        ]),
        UI::card('Welcome', UI::alert('No templates. No build step. Just PHP.', 'info')),
    )
    ->response();
```

No `.blade`, no webpack, no `package.json`. The component renders themed,
responsive HTML with transitions, dark/light mode and i18n built in.

## What's in the box

| | |
|---|---|
| 🧩 **46+ UI components** | Cards, tables, forms, charts, modals, drawers, tabs… pure PHP, theme-aware, `->color()` any of them |
| 🛡️ **Secure by default** | Auth-by-default `/admin`, CSRF, secure session cookies, security headers, AES-256, hashing — out of the box |
| 🗄️ **ORM + migrations** | ActiveRecord, query builder, relations, eager loading, seeders, factories |
| 🔎 **Search engine** | Zero-dependency inverted index, `Searchable` models, a searchable `UI::combobox` |
| 💬 **Real-time messaging** | In-admin user-to-user chat (`UI::chat`) — 1:1 & group channels, WebSocket or polling |
| 🔔 **Browser notifications** | Desktop notifications **with sound** (synthesized, zero-asset) from any `$user->notify()` |
| 🎛️ **Admin panel** | `/admin` with menu, widgets, DB browser, SQL console, one-click updates |
| ⚡ **Fast in production** | opcache preload, route/config cache, a UI asset compiler — `php sofy optimize` |
| 🚀 **One-command deploy** | `sudo php sofy full-install` provisions a whole Linux box; services run under systemd |
| 🧰 **Rich CLI** | Scaffolding, migrations, queue, scheduler, REPL, service management |
| 📦 **Modules & marketplace** | Drop a folder in `modules/`, install from a catalog |
| ✅ **Tested** | PHPUnit core suite — `composer test` |

## Quick start

```bash
composer create-project sofyphp/framework my-app   # or: git clone … && composer install
cd my-app
cp .env.example .env
php sofy key:generate
php sofy migrate
php sofy admin:create        # interactive admin user
php -S localhost:8000 -t public
```

Open <http://localhost:8000> — visit `/admin` (login at `/admin/login`),
the live component reference at `/ui-demo`, and the docs at `/docs`.

```php
// routes/web.php
$router->get('/hello/{name}', fn(Request $r, string $name): Response =>
    UI::page("Hello, $name!")
        ->add(UI::hero("Hello, $name!", 'Welcome to Sofy.')
            ->action(UI::button('Get started', '/docs', 'primary')))
        ->response()
);
```

## Production in one command

```bash
sudo php sofy full-install        # interactive: domain, PHP, web server, SSL, DB, services
```

The wizard installs PHP + extensions, your web server (**Caddy** / Nginx /
Apache) with HTTPS, the database, sets permissions, runs migrations, and brings
up **background services under systemd** — queue worker, WebSocket server and
Redis — so the box comes up fully running. Add or manage them anytime:

```bash
sudo php sofy service:install all   # redis + ws + queue + scheduler
php sofy service:status
```

Then squeeze out the throughput:

```bash
php sofy optimize     # route + config cache + opcache preload
php sofy ui:build      # extract CSS/JS into cached static assets
```

## Requirements

- **PHP 8.3+** · extensions: `pdo`, `curl`, `mbstring`, `openssl`, `fileinfo`, `simplexml`
- Optional: `redis` (cache/session/broadcast), `pcntl` (WebSocket graceful shutdown)
- Production installer: Linux + root

## Documentation

Full docs in [`docs/`](docs/) (browsable in-app at `/docs`):

[Getting started](docs/01-getting-started.md) ·
[Routing](docs/02-routing.md) ·
[Views & UI](docs/04-views.md) ·
[Database](docs/05-database.md) ·
[Auth](docs/06-auth.md) ·
[Performance](docs/16-performance.md) ·
[Search](docs/17-search.md) ·
[Messenger](docs/18-messenger.md) ·
[Notifications](docs/19-notifications.md)

Release notes: [`CHANGELOG.md`](CHANGELOG.md) (also at `/admin/system/update`).

## License

[MIT](LICENSE) — © Sofy contributors.
