# Начало работы

## Установка

```bash
git clone <repo> sofy
cd sofy
composer install
cp .env.example .env
php sofy key:generate
```

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
