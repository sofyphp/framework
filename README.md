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
- **UI components** — 40+ pure-PHP components (cards, tables, forms, charts, modals, …) with transitions, dark/light themes and i18n
- **Modules** — drop a folder in `modules/` and it auto-registers routes, services and commands
- **Console** — `sofy` CLI with scheduler support
- **Testing** — test case + HTTP/queue/event/mail/storage fakes

## Installation

```bash
# Create a new project (once published to Packagist)
composer create-project sofyphp/framework my-app

# …or clone and install
git clone https://github.com/sofyphp/framework my-app
cd my-app
composer install

cp .env.example .env
php sofy key:generate
php -S localhost:8000 -t public
```

Open <http://localhost:8000>.

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
                UI::stat('Users', 1240, '+5%'),
                UI::stat('Active', 983, '+2%'),
                UI::stat('New', 42, '+12%'),
            ]),
        )
        ->response();
});
```

## Documentation

Full documentation lives in [`docs/`](docs/) and is browsable in-app at `/docs`.
A live UI component reference is available at `/ui-demo`.

## License

[MIT](LICENSE)
