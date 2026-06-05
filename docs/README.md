# Sofy Framework — Документация

Sofy — минималистичный PHP 8.3 MVC-фреймворк без сторонних зависимостей во время выполнения.

## Содержание

| # | Раздел | Описание |
|---|--------|---------|
| 1 | [Начало работы](01-getting-started.md) | Установка, структура, конфигурация |
| 2 | [Роутинг](02-routing.md) | Маршруты, группы, именованные URL |
| 3 | [Контроллеры и запросы](03-controllers-requests.md) | Контроллеры, Request, FormRequest, Response |
| 4 | [Шаблоны и UI](04-views.md) | Компилятор шаблонов, директивы, UI-компоненты, иконки |
| 5 | [База данных](05-database.md) | ORM, QueryBuilder, отношения, миграции, фабрики |
| 6 | [Аутентификация и авторизация](06-auth.md) | Auth, Gate, Policy, роли, API-токены |
| 7 | [Кэш и сессии](07-cache-session.md) | Cache (file/redis/array), Session |
| 8 | [Очереди](08-queue.md) | Job, Queue, Worker |
| 9 | [События и вещание](09-events-broadcasting.md) | Dispatcher, Broadcasting, SSE, WebSocket |
| 10 | [Почта и уведомления](10-mail-notifications.md) | Mailable, Mailer, Notification, каналы |
| 11 | [Тестирование](11-testing.md) | TestCase, TestResponse, Fakes |
| 12 | [Консоль](12-console.md) | CLI, все команды, REPL, создание своих |
| 13 | [Модули](13-modules.md) | Модульная архитектура, Module, ModuleLoader |
| 14 | [Хранилище и утилиты](14-advanced.md) | Storage, HTTP-клиент, валидация, утилиты |
| 15 | [Админ-панель](15-admin.md) | `/admin`, логин, меню, виджеты, БД-браузер, апдейтер |
| 16 | [Производительность](16-performance.md) | opcache preload, route/config cache, `php sofy optimize`, UI-компилятор |
| 17 | [Поиск](17-search.md) | Движок поиска, инвертированный индекс, `Searchable`, `UI::combobox` |
| 18 | [Мессенджер](18-messenger.md) | Переписка в админке, `UI::chat`, 1:1 и групповые каналы, WebSocket |
| 19 | [Уведомления](19-notifications.md) | Браузерные desktop-уведомления со звуком, `sofyNotify`, лента |

> Спецификация модуля маркетплейса — [sofy-module-spec.md](sofy-module-spec.md).

## Быстрый старт

```bash
# Клонировать / скачать проект
cd sofy

# Установить зависимости (только PHPUnit для тестов)
composer install

# Скопировать конфиг
cp .env.example .env

# Сгенерировать ключ приложения
php sofy key:generate

# Запустить миграции
php sofy migrate

# Создать админа (интерактивно)
php sofy admin:create

# Встроенный PHP-сервер
php -S localhost:8000 -t public

# Прогнать тесты ядра
composer test
```

Открой <http://localhost:8000> и зайди в `/admin` (логин — `/admin/login`).

**Для продакшена** — скомпилируй кэши и ассеты:

```bash
php sofy optimize     # route + config cache + opcache preload-скрипт
php sofy ui:build      # вынести CSS/JS в кэшируемые статические файлы
```

## Исходники

GitHub: <https://github.com/sofyphp/framework>

## Основные принципы

- **Нет сторонних пакетов** в runtime — только PSR-4 автозагрузка через Composer
- **Пространство имён приложения** — `Main\` (папка `Main/`)
- **Конфиг** — файлы `config/*.php`, переменные окружения `.env`
- **PHP 8.3+** — readonly, match, named arguments, fibers
- **Тесты** — `composer test` (PHPUnit 11, in-memory SQLite)

## Релизы

Полный список изменений — в [CHANGELOG.md](../CHANGELOG.md) на корне проекта.
В админке тот же фид доступен на `/admin/system/update` — с бэйджами
`installed` / `newer` / `older` и кнопкой обновления одним кликом.
