# Консоль

## Встроенные команды

### Приложение

| Команда | Описание |
|---------|---------|
| `php sofy list` | Список всех команд |
| `php sofy key:generate` | Сгенерировать APP_KEY |
| `php sofy down` | Режим обслуживания |
| `php sofy up` | Выйти из режима обслуживания |
| `php sofy repl` | Интерактивный PHP REPL ([см. ниже](#interaktivnyy-repl)) |
| `sudo php sofy full-install` | Установка на чистый Linux-сервер (мастер) — [подробнее](#polnaya-ustanovka-full-install) |
| `sudo php sofy full-install --no-interaction` | Та же установка с дефолтами без вопросов |
| `php sofy update` | Обновить фреймворк до последней версии на Packagist |
| `php sofy update --version=0.4.5` | Обновить до конкретной версии |
| `php sofy update --dry-run` | Показать diff без применения |
| `php sofy update --no-interaction --no-composer` | Web-trigger режим (используется `/admin/system/update`) |
| `php sofy admin:create` | Создать пользователя-админа (интерактивно) |

### Маршруты

| Команда | Описание |
|---------|---------|
| `php sofy route:list` | Список всех маршрутов |
| `php sofy route:cache` | Кэшировать маршруты |
| `php sofy route:clear` | Очистить кэш маршрутов |

### Конфигурация и кэш

| Команда | Описание |
|---------|---------|
| `php sofy config:cache` | Кэшировать конфиг |
| `php sofy config:clear` | Очистить кэш конфига |
| `php sofy cache:clear` | Очистить application cache |
| `php sofy view:clear` | Очистить скомпилированные шаблоны |

### Миграции

| Команда | Описание |
|---------|---------|
| `php sofy migrate` | Выполнить новые миграции |
| `php sofy migrate:rollback` | Откат последнего батча |
| `php sofy migrate:rollback --steps=3` | Откат N батчей |
| `php sofy migrate:fresh` | Drop all + migrate |
| `php sofy migrate:status` | Статус миграций |

### Сидеры

| Команда | Описание |
|---------|---------|
| `php sofy db:seed` | Запустить DatabaseSeeder |
| `php sofy db:seed --class=UserSeeder` | Конкретный сидер |

### Очереди

| Команда | Описание |
|---------|---------|
| `php sofy queue:work` | Запустить воркер |
| `php sofy queue:work --queue=emails` | Конкретная очередь |
| `php sofy queue:work --sleep=5` | Пауза при пустой очереди |
| `php sofy queue:retry` | Повторить упавшие задачи |
| `php sofy queue:flush` | Удалить упавшие задачи |
| `php sofy queue:table` | Создать миграцию таблицы jobs |

### Планировщик

| Команда | Описание |
|---------|---------|
| `php sofy schedule:run` | Выполнить задачи расписания (cron 1 min) |
| `php sofy schedule:list` | Показать расписание |

### WebSocket

| Команда | Описание |
|---------|---------|
| `php sofy ws:serve` | Запустить WebSocket-сервер |

### Генераторы кода

| Команда | Описание |
|---------|---------|
| `php sofy make:controller {name}` | Контроллер |
| `php sofy make:model {name}` | Модель |
| `php sofy make:migration {name}` | Миграция |
| `php sofy make:request {name}` | FormRequest |
| `php sofy make:resource {name}` | JSON Resource |
| `php sofy make:job {name}` | Job |
| `php sofy make:event {name}` | Event |
| `php sofy make:mail {name}` | Mailable |
| `php sofy make:notification {name}` | Notification |
| `php sofy make:policy {name}` | Policy |
| `php sofy make:middleware {name}` | Middleware |
| `php sofy make:seeder {name}` | Seeder |
| `php sofy make:factory {name}` | Factory |
| `php sofy make:observer {name} {--model=}` | Observer |
| `php sofy make:test {name} {--unit}` | Тест |
| `php sofy make:module {name}` | Модуль |

---

## Создание своей команды

```php
// Main/Commands/SendReportCommand.php
namespace Main\Commands;

use Sofy\Console\Command;

class SendReportCommand extends Command
{
    protected string $signature   = 'report:send {type} {--daily} {--email=}';
    protected string $description = 'Send a report';

    public function handle(): int
    {
        $type  = $this->argument('type');
        $daily = $this->option('daily');       // bool
        $email = $this->option('email');       // string|null

        if (!$type) {
            $this->error('Missing argument: type');
            return 1;
        }

        $this->info("Sending $type report...");

        // ... логика ...

        $this->success('Done!');
        return 0;
    }
}
```

### Синтаксис сигнатуры

```
report:send {type} {optional?} {--flag} {--option=} {--opt=default}
```

| Токен | Тип |
|-------|-----|
| `{name}` | Обязательный аргумент |
| `{name?}` | Необязательный аргумент |
| `{--flag}` | Булева опция (`--flag` → true) |
| `{--option=}` | Опция со значением |
| `{--option=default}` | Опция с дефолтом |

### Вывод

```php
$this->info('Информация');          // синий
$this->success('Успех');            // зелёный
$this->warn('Предупреждение');      // жёлтый
$this->error('Ошибка');             // красный
$this->comment('Комментарий');      // серый
$this->line('Обычный текст');
```

### Интерактивный ввод

```php
$name  = $this->ask('Как вас зовут?');
$ok    = $this->confirm('Продолжить?', default: true);
$env   = $this->select('Окружение:', ['local', 'staging', 'production'], 'local');
```

### Регистрация команды

В `routes/console.php`:

```php
$kernel->register(\Main\Commands\SendReportCommand::class);
```

Или из модуля в `Module::commands()`:

```php
public function commands(): array
{
    return [SendReportCommand::class];
}
```

---

## Планировщик задач

```php
// routes/schedule.php
use Sofy\Console\Schedule;

Schedule::command('report:send daily')->daily();
Schedule::command('cache:clear')->hourly();
Schedule::command('queue:flush')->weekly();

// Произвольное расписание (cron-выражение)
Schedule::command('report:send monthly')->cron('0 9 1 * *');

// Замыкание
Schedule::call(fn() => DB::table('sessions')->where('expired_at', '<', now())->delete())
    ->everyFiveMinutes();
```

---

## Полная установка `full-install`

`sudo php sofy full-install` — мастер развёртывания на чистый сервер
Ubuntu/Debian. За шесть шагов ставит PHP, веб-сервер, БД, SSL, cron,
Supervisor и накатывает миграции. Linux-only, требует root.

### Что произойдёт

Перед стартом мастер делает pre-flight (Linux + root) и собирает конфиг:

| Шаг | Опции |
|---|---|
| **1. Domain & Environment** | `example.com` или `1.2.3.4` (IP/localhost — будет self-signed cert) |
| **2. PHP Version** | `8.3` / `8.4` / `8.5` — если версия не в distro-репах, автоматически добавится Ondřej PPA |
| **3. Web Server** | **Caddy** (рекомендуется), Nginx, Apache |
| **4. SSL Certificate** | Caddy: авто Let's Encrypt; Nginx/Apache: Certbot + email |
| **5. Database** | **MySQL/MariaDB**, PostgreSQL, SQLite + имя БД, юзер, пароль |
| **6. Additional Components** | Cron для `schedule:run`, Supervisor для `queue:work`, миграции |

После сводки и `Proceed? [Y/n]` начинается установка — ставится PHP +
extensions, Composer, выбранный веб-сервер с готовым vhost (`document
root → public/`, PHP-FPM сокет, rewrite-правила), создаётся база и
пользователь со сгенерированным паролем (печатается в выводе),
конфигурируются права на `storage/` и `bootstrap/cache/`, прописывается
cron-строка и Supervisor-программа, запускаются миграции.

### Без вопросов

```bash
sudo php sofy full-install --no-interaction
```

Использует дефолты: `domain=localhost`, `php=8.5`, `webserver=caddy`,
`ssl=true`, `db=mysql`, `db_name=sofy`, `db_user=sofy`, пароль
автогенерируется, `cron+supervisor+migrate=true`. Подходит для CI или
provisioning-скриптов.

### Что не делает

- Не меняет `composer.json` приложения — только устанавливает зависимости
- Не трогает существующий `.env` (если есть — пропускает шаг и сообщает)
- Не накатывает ничего на нелинуксе (macOS/Windows) — выходит с ошибкой
- Не работает без root — попросит `sudo php sofy full-install`

### Альтернатива: ручная установка

Для девелопмента или нестандартного окружения проще руками:

```bash
git clone https://github.com/sofyphp/framework my-app && cd my-app
composer install
cp .env.example .env
php sofy key:generate
php sofy migrate
php sofy admin:create
php -S localhost:8000 -t public
```

См. [Начало работы](01-getting-started.md) — там обе ветки расписаны
подробнее.

---

## Интерактивный REPL

`php sofy repl` — встроенный PSI/Tinker-аналог: PHP REPL с автозагруженным
фреймворковым контекстом (модели, фасады, хелперы).

```text
$ php sofy repl

  Sofy REPL  (PHP 8.3.13 — type 'help' for commands, Ctrl+D to exit)

>>> Main\Models\User::count()
=> 3
>>> $u = Main\Models\User::find(1)
>>> $u->email
=> "admin@example.com"
>>> Sofy\Cache\Cache::remember('answer', 60, fn () => 42)
=> 42
```

Что умеет:

- **Авто-возврат** — выражения вроде `User::find(1)` сразу печатают результат,
  `echo` не нужен. Если строку не получилось распарсить как выражение —
  выполняется как обычный statement.
- **Мультистрочный ввод** — токенайзер считает скобки, prompt меняется на
  `... ` пока буфер не замкнётся (`{ ( [`).
- **readline-история** — стрелки ↑/↓, поиск, файл `~/.sofy_repl_history`
  (нужен `ext-readline`).
- **Цветной dump** — `null`/`bool`/`int`/`string`/`array`/`object` подсвечены;
  объекты с методом `toArray()` печатают свои поля, всё остальное — `Class{…}`.
- **Спец-команды** — `help`, `clear`, `exit` / `quit` / `q`, Ctrl+D.

Аналог в админке — `/admin/database/sql` для прямых SQL-запросов.

---

Добавить в crontab:

```bash
* * * * * php /var/www/sofy queue:work --queue=default >> /dev/null 2>&1
* * * * * php /var/www/sofy schedule:run >> /dev/null 2>&1
```
