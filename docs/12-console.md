# Консоль

## Встроенные команды

### Приложение

| Команда | Описание |
|---------|---------|
| `php sofy list` | Список всех команд |
| `php sofy key:generate` | Сгенерировать APP_KEY |
| `php sofy down` | Режим обслуживания |
| `php sofy up` | Выйти из режима обслуживания |
| `php sofy repl` | Интерактивный PHP REPL |
| `php sofy install` | Полная установка (migrate + seed) |

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

Добавить в crontab:

```bash
* * * * * php /var/www/sofy queue:work --queue=default >> /dev/null 2>&1
* * * * * php /var/www/sofy schedule:run >> /dev/null 2>&1
```
