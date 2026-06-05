<div align="center">

# So<span>fy</span>

### PHP-фреймворк «всё включено» с **нулём зависимостей в рантайме**

Собери весь продукт — UI, админку, авторизацию, поиск, чат в реальном времени,
уведомления — на чистом PHP. Без node, без шаблонов, без сборки, без vendor-балласта.

[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-22c55e)](LICENSE)
[![Dependencies](https://img.shields.io/badge/runtime%20deps-0-ff6b3d)](composer.json)
[![Tests](https://img.shields.io/badge/tests-PHPUnit-3b82f6)](tests)

[English](README.md) · **Русский** · [Документация](docs/) · демо UI на `/ui-demo`

</div>

---

## Зачем Sofy?

Большинство фреймворков дают роутер и стопку Composer-пакетов, а за UI отправляют
в npm. **Sofy поставляет готовый продукт** — и рендерит стилизованный HTML прямо
из PHP.

```php
return UI::page('Дашборд')
    ->add(
        UI::grid(3, [
            UI::stat('Выручка', '$42k')->color('#10b981'),
            UI::stat('Юзеры',    1240, '+5%'),
            UI::stat('Активные',  983, '+2%'),
        ]),
        UI::card('Привет', UI::alert('Ни шаблонов, ни сборки. Просто PHP.', 'info')),
    )
    ->response();
```

Никаких `.blade`, webpack или `package.json`. Компонент рендерит тематизированный
адаптивный HTML — с переходами, тёмной/светлой темой и i18n из коробки.

## Что внутри

| | |
|---|---|
| 🧩 **46+ UI-компонентов** | Карточки, таблицы, формы, графики, модалки, дровера, табы… чистый PHP, тема, любому `->color()` |
| 🛡️ **Безопасность по умолчанию** | `/admin` закрыт авторизацией, CSRF, secure-cookie сессий, security-заголовки, AES-256, хеширование |
| 🗄️ **ORM + миграции** | ActiveRecord, query builder, связи, eager loading, сидеры, фабрики |
| 🔎 **Поисковый движок** | Инвертированный индекс без зависимостей, трейт `Searchable`, searchable `UI::combobox` |
| 💬 **Чат в реальном времени** | Переписка между пользователями в админке (`UI::chat`) — 1:1 и группы, WebSocket или polling |
| 🔔 **Браузерные уведомления** | Desktop-уведомления **со звуком** (синтез, без файлов) из любого `$user->notify()` |
| 🎛️ **Админ-панель** | `/admin` с меню, виджетами, браузером БД, SQL-консолью, обновлением в один клик |
| ⚡ **Быстро в проде** | opcache preload, кэш роутов/конфига, компилятор UI-ассетов — `php sofy optimize` |
| 🚀 **Деплой одной командой** | `sudo php sofy full-install` поднимает весь Linux-сервер; сервисы под systemd |
| 🧰 **Богатый CLI** | Генераторы, миграции, очереди, шедулер, REPL, управление сервисами |
| 📦 **Модули и маркетплейс** | Кинул папку в `modules/`, ставь из каталога |
| ✅ **Покрыт тестами** | Core-сьют на PHPUnit — `composer test` |

## Быстрый старт

```bash
composer create-project sofyphp/framework my-app   # или: git clone … && composer install
cd my-app
cp .env.example .env
php sofy key:generate
php sofy migrate
php sofy admin:create        # интерактивно создать админа
php -S localhost:8000 -t public
```

Открой <http://localhost:8000> — зайди в `/admin` (вход `/admin/login`),
живой справочник компонентов на `/ui-demo` и доки на `/docs`.

```php
// routes/web.php
$router->get('/hello/{name}', fn(Request $r, string $name): Response =>
    UI::page("Привет, $name!")
        ->add(UI::hero("Привет, $name!", 'Добро пожаловать в Sofy.')
            ->action(UI::button('Начать', '/docs', 'primary')))
        ->response()
);
```

## Прод одной командой

```bash
sudo php sofy full-install        # интерактивно: домен, PHP, веб-сервер, SSL, БД, сервисы
```

Визард ставит PHP + расширения, веб-сервер (**Caddy** / Nginx / Apache) с HTTPS,
базу, права, миграции и поднимает **фоновые сервисы под systemd** — queue-воркер,
WebSocket-сервер и Redis — так что сервер встаёт полностью рабочим. Добавить или
управлять в любой момент:

```bash
sudo php sofy service:install all   # redis + ws + queue + scheduler
php sofy service:status
```

Затем выжми throughput:

```bash
php sofy optimize     # кэш роутов + конфига + opcache preload
php sofy ui:build      # вынести CSS/JS в кэшируемые статические файлы
```

## Требования

- **PHP 8.3+** · расширения: `pdo`, `curl`, `mbstring`, `openssl`, `fileinfo`, `simplexml`
- Опционально: `redis` (cache/session/broadcast), `pcntl` (graceful-выключение WebSocket)
- Прод-инсталлятор: Linux + root

## Документация

Полные доки в [`docs/`](docs/) (читаются в приложении на `/docs`):

[Начало работы](docs/01-getting-started.md) ·
[Роутинг](docs/02-routing.md) ·
[Шаблоны и UI](docs/04-views.md) ·
[База данных](docs/05-database.md) ·
[Авторизация](docs/06-auth.md) ·
[Производительность](docs/16-performance.md) ·
[Поиск](docs/17-search.md) ·
[Мессенджер](docs/18-messenger.md) ·
[Уведомления](docs/19-notifications.md)

Changelog: [`CHANGELOG.md`](CHANGELOG.md) (он же на `/admin/system/update`).

## Лицензия

[MIT](LICENSE) — © участники Sofy.
