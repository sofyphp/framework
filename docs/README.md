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
| 15 | [Админ-панель](15-admin.md) | `/admin`, меню, виджеты, БД-браузер, апдейтер |

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
```

Открой <http://localhost:8000> и зайди в `/admin`.

## Основные принципы

- **Нет сторонних пакетов** в runtime — только PSR-4 автозагрузка через Composer
- **Пространство имён приложения** — `Main\` (папка `Main/`)
- **Конфиг** — файлы `config/*.php`, переменные окружения `.env`
- **PHP 8.3+** — readonly, match, named arguments, fibers

## Релизы

Полный список изменений — в [CHANGELOG.md](../CHANGELOG.md) на корне проекта.
В админке тот же фид доступен на `/admin/system/update` — с бэйджами
`installed` / `newer` / `older` и кнопкой обновления одним кликом.
