# Начало работы

## Установка

Два сценария: локальная разработка и развёртывание на чистый сервер одной
командой.

### Локально (для разработки)

```bash
# Через Packagist
composer create-project sofyphp/framework my-app

# Либо вручную из git
git clone https://github.com/sofyphp/framework my-app
cd my-app
composer install

cp .env.example .env
php sofy key:generate
php sofy migrate
php sofy admin:create                # интерактивно создаёт админа
php -S localhost:8000 -t public
```

Открой <http://localhost:8000> и зайди в `/admin`.

### Продакшен — мастер `full-install`

Sofy умеет провижить себя на свежий Linux-сервер одной командой. Подходит
для Ubuntu/Debian-семейства.

```bash
sudo php sofy full-install                    # интерактивный мастер
sudo php sofy full-install --no-interaction   # тихая установка с дефолтами
```

Мастер проводит через **6 шагов**, выводит сводку и просит подтверждения
перед началом:

| Шаг | Что спрашивает |
|---|---|
| 1 — Domain & Environment | домен или IP (`example.com` / `1.2.3.4`) |
| 2 — PHP Version          | `8.3` / `8.4` / `8.5` (через Ondřej PPA, если нужно) |
| 3 — Web Server           | **Caddy** (рекомендуется) / Nginx / Apache |
| 4 — SSL Certificate      | для Caddy — авто Let's Encrypt; для Nginx/Apache — Certbot c email |
| 5 — Database             | **MySQL** / PostgreSQL / SQLite + имя БД, юзер, пароль |
| 6 — Additional           | cron `schedule:run`, Supervisor `queue:work`, `migrate` после |

**Что устанавливается и настраивается:**

- PHP выбранной версии + расширения (`pdo`, `curl`, `mbstring`, `openssl`,
  `fileinfo`, `simplexml`, `xml`, `zip`, PDO-драйвер по выбранной БД)
- Composer (если не было)
- Веб-сервер с готовым vhost: document root → `public/`, PHP-FPM сокет,
  rewrite-правила
- Caddy: HTTPS работает сразу — авто-renew Let's Encrypt для реальных
  доменов, self-signed для `localhost`/IP
- Nginx/Apache: Certbot ставится и сразу запрашивает сертификат (если
  домен — реальный публичный hostname)
- БД: создаётся база и пользователь со сгенерированным паролем, креды
  записываются в `.env` (`DB_DRIVER` / `DB_DATABASE` / `DB_USERNAME` /
  `DB_PASSWORD`)
- Права: `storage/` и `bootstrap/cache/` получают корректного владельца
- Cron: строка `* * * * * php sofy schedule:run` в `/etc/cron.d/sofy`
- Supervisor: программа, которая держит `php sofy queue:work` живым с
  автоматическим рестартом
- Финальный `php sofy migrate`

**Дефолты `--no-interaction`:** `domain=localhost`, `php=8.5`,
`webserver=caddy`, `ssl=true (auto)`, `db=mysql`, `db_name=sofy`,
`db_user=sofy`, пароль автогенерируется и печатается в выводе,
`cron+supervisor+migrate=true`.

**Pre-flight:** команда работает только на Linux и требует прав root.
На macOS/Windows скажет про это и выйдет.

```bash
sudo php sofy full-install
# ...
# ╔══════════════════════════════════════════╗
# ║       Installation complete!             ║
# ╚══════════════════════════════════════════╝
#
# Useful commands:
#   php sofy migrate:status
#   php sofy admin:create
#   sudo systemctl status caddy php8.5-fpm
#   tail -f storage/logs/app.log
```

После установки можешь создать админа (`php sofy admin:create`) и зайти
в `/admin` — всё уже работает.

### Обновление существующей установки

```bash
php sofy update                       # последний стабильный с Packagist
php sofy update --version=0.5.0       # конкретная версия
php sofy update --dry-run             # показать diff без применения
```

Те же шаги доступны из админки на `/admin/system/update` — кнопкой Update
now. Подробности — в [docs/15-admin.md](15-admin.md).


## Структура проекта

```
sofy/
├── bootstrap/
│   └── app.php            # точка входа приложения
├── config/                # конфигурационные файлы
│   ├── app.php
│   ├── database.php
│   ├── cache.php
│   ├── session.php
│   ├── queue.php
│   ├── mail.php
│   ├── cors.php
│   └── ...
├── database/
│   ├── migrations/        # файлы миграций
│   └── seeds/             # сидеры
├── docs/                  # документация
├── lang/                  # локализация
│   └── en/
├── Main/                  # код приложения (namespace Main\)
│   ├── Controllers/
│   ├── Models/
│   ├── Factories/
│   ├── Events/
│   ├── Observers/
│   └── ...
├── modules/               # модули (namespace Modules\)
│   ├── Blog/
│   └── Shop/
├── public/
│   └── index.php          # точка входа HTTP
├── routes/
│   ├── web.php            # веб-маршруты
│   ├── api.php            # API-маршруты
│   ├── console.php        # консольные команды
│   └── schedule.php       # планировщик
├── src/                   # ядро фреймворка (namespace Sofy\)
├── storage/
│   ├── cache/
│   ├── logs/
│   └── views/             # скомпилированные шаблоны
├── tests/                 # тесты
│   ├── Feature/
│   └── Unit/
├── views/                 # шаблоны приложения
└── sofy                   # CLI-скрипт
```

## Конфигурация

### .env

```ini
APP_NAME=Sofy
APP_ENV=local
APP_DEBUG=true
APP_KEY=                   # заполняется командой key:generate
APP_URL=http://localhost

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sofy
DB_USERNAME=root
DB_PASSWORD=

CACHE_DRIVER=file          # file | redis | array
SESSION_DRIVER=file        # file | redis
QUEUE_DRIVER=sync          # sync | database

MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM=noreply@example.com

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

BROADCAST_DRIVER=log       # redis | log | null
```

### bootstrap/app.php

```php
<?php

$app = new \Sofy\Core\Application(dirname(__DIR__));
$app->loadModules();   // авто-обнаружение modules/
$app->boot();
return $app;
```

### public/index.php

```php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

$app      = require dirname(__DIR__) . '/bootstrap/app.php';
$request  = \Sofy\Http\Request::capture();
$response = $app->handle($request);
$response->send();
```

## Контейнер зависимостей

```php
// Регистрация
$app->bind(MailerInterface::class, Mailer::class);
$app->singleton(Cache::class, fn() => new Cache());

// Получение
$mailer = app(MailerInterface::class);
$mailer = $app->get(MailerInterface::class);
```

## Жизненный цикл запроса

1. `public/index.php` → `Application::boot()`
2. `Application::handle(Request)` → Middleware Pipeline
3. Router → Controller → Response
4. `Response::send()` → заголовки + тело

## Обработка ошибок

При `APP_DEBUG=true` в `.env` любое необработанное исключение (в том числе
fatal-ошибки и ошибки на этапе boot — например, отсутствующий PSR-4 для
свежескопированного модуля) отрисовывается в **debug-странице**: класс
исключения, сообщение, файл/строка, исходник вокруг точки падения, полный
stack trace с разворачивающимися фреймами + Request-инспектор (GET / POST
/ Headers / Server).

Хендлер ставится в `Application::__construct` тремя слоями — закрывает
весь жизненный цикл запроса, а не только `Application::handle()`:

1. `set_error_handler` — превращает PHP notice/warning в `ErrorException`
2. `set_exception_handler` — ловит throwable вне try/catch (включая
   `loadModules()` и `boot()`)
3. `register_shutdown_function` — добивает фатальные `E_ERROR` / `E_PARSE`,
   которые userland не может поймать иначе

При `APP_DEBUG=false` отдаётся либо `resources/views/errors/{status}.php`
если файл есть, либо стандартный `Internal Server Error` без деталей.

Создать `views/errors/{code}.php` (или `.sofy.php`):

```
views/errors/404.php
views/errors/500.php
```

Или зарегистрировать свой обработчик в `bootstrap/app.php`:

```php
$app->onError(404, function(\Sofy\Http\HttpException $e) {
    return \Sofy\Http\Response::view('errors.404', [], 404);
});
```
