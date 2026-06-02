# Sofy

**Minimal, dependency-free PHP 8.3 MVC framework.** No Composer runtime packages —
just PHP and your code. Ships a pure-PHP UI component system that renders styled
HTML (no templates, no build step) with built-in transitions and a soft visual theme.

## Requirements

- PHP **8.3+**
- Extensions: `pdo`, `curl`, `mbstring`, `openssl`, `fileinfo`, `simplexml`
- Optional: `redis` (Redis cache/session), `pcntl` (graceful WebSocket shutdown)

## Features

- **Routing** — expressive routes, route groups, middleware, named routes, resource routes
- **Container** — autowiring DI container with reflection caching
- **ORM** — ActiveRecord models, query builder, relations, eager loading, migrations, seeders, factories
- **Auth & security** — auth, gates/policies, roles, API tokens, CSRF, AES‑256 encryption, hashing
- **Validation** — rule-based validator with form requests
- **Queue** — sync / database drivers with a worker
- **Events & broadcasting** — dispatcher, broadcast drivers, built-in WebSocket server
- **Cache** — file / redis / array drivers
- **Mail & notifications** — mailer, mailables, mail / database / broadcast channels
- **Storage** — local filesystem and S3 drivers
- **UI components** — 50+ pure-PHP components (cards, tables, forms, charts, modals, …) with transitions, dark/light themes and i18n
- **Icon library** — `Sofy\View\Icons`: 107 Feather-style SVGs, `UI::icon('home', size: 20)`
- **Admin panel** — built-in CMS-like `/admin` with module-extensible menu, widgets, database browser, SQL console, system info and one-click framework updates
- **REPL** — `php sofy repl` for an interactive PHP shell with full framework context
- **Modules** — drop a folder in `modules/` and it auto-registers routes, services and commands
- **Console** — `sofy` CLI with scheduler support
- **Testing** — test case + HTTP/queue/event/mail/storage fakes

## Installation

Two install paths — local development and a turnkey production wizard.

### Development (local)

```bash
# Create a new project (published on Packagist)
composer create-project sofyphp/framework my-app

# …or clone and install
git clone https://github.com/sofyphp/framework my-app
cd my-app
composer install

cp .env.example .env
php sofy key:generate
php sofy migrate
php sofy admin:create               # interactive admin user
php -S localhost:8000 -t public
```

Open <http://localhost:8000>, then visit `/admin`.

### Production (`full-install` wizard)

Sofy ships a single-command server installer for fresh Linux boxes (Ubuntu /
Debian-family):

```bash
sudo php sofy full-install                    # interactive wizard (recommended)
sudo php sofy full-install --no-interaction   # silent install with defaults
```

The wizard walks through six steps — domain, PHP version (8.3 / 8.4 / 8.5),
web server (**Caddy** / Nginx / Apache), SSL (auto via Caddy + Let's Encrypt
or Certbot for Nginx/Apache), database driver (**MySQL** / PostgreSQL /
SQLite) with credentials, and finally cron + Supervisor + migrations. It
prints a summary and asks for confirmation before touching anything.

What it does:

- Installs PHP (via Ondřej PPA when the requested version isn't in the
  distro's repos), Composer, and all required extensions
- Installs and configures the chosen web server with a correct vhost +
  document root + PHP-FPM unix socket
- For Caddy: HTTPS works out of the box (auto-renewing Let's Encrypt
  certificate, or a self-signed cert when domain is `localhost`/IP)
- For Nginx/Apache: installs Certbot and obtains a Let's Encrypt cert
  (skipped if the domain isn't a real public hostname)
- Creates the database + user with a generated password and writes the
  credentials to `.env`
- Sets correct ownership/permissions on `storage/` and `bootstrap/cache/`
- Installs a cron entry for `php sofy schedule:run` (every minute) and a
  Supervisor program that keeps `php sofy queue:work` alive
- Runs `php sofy migrate`

Defaults used by `--no-interaction`: `domain=localhost`, `php=8.5`,
`webserver=caddy`, `ssl=auto`, `db=mysql`, `db_name=sofy`, `db_user=sofy`,
auto-generated password, cron+supervisor+migrate all on.

Run as `root` (or with `sudo`) — it touches `/etc/`, `/var/`, package
manager and systemd. Linux-only.

## Quick start

```php
// routes/web.php
use Sofy\Http\Request;
use Sofy\Http\Response;
use Sofy\View\UI;

$router->get('/hello/{name}', function (Request $req, string $name): Response {
    return UI::page("Hello, $name!")
        ->nav('MyApp', ['/' => 'Home'])
        ->add(
            UI::hero("Hello, $name!", 'Welcome to Sofy.')
                ->action(UI::button('Get started', '/docs', 'primary')),
            UI::grid(3, [
                UI::stat('Users',  1240, '+5%'),
                UI::stat('Active',  983, '+2%'),
                UI::stat('New',      42, '+12%'),
            ]),
            UI::card('Tip',
                UI::raw('Use ' . UI::icon('zap', size: 14) . ' <code>UI::icon()</code> for theme-aware SVG glyphs.'),
            ),
        )
        ->response();
});
```

## Documentation

Full documentation lives in [`docs/`](docs/) and is browsable in-app at `/docs`.
A live UI component reference is available at `/ui-demo`. Release notes live in
[`CHANGELOG.md`](CHANGELOG.md) and are surfaced inside the admin at
`/admin/system/update`.

## License

[MIT](LICENSE)
