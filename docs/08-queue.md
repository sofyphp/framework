# Очереди

Фреймворк поддерживает два драйвера: `sync` (выполняет немедленно, без воркера) и `database` (хранит задачи в таблице `jobs`).

## Создание задачи

```php
// Main/Jobs/SendWelcomeEmail.php
namespace Main\Jobs;

use Sofy\Queue\Job;
use Main\Models\User;

class SendWelcomeEmail extends Job
{
    public string $queue       = 'emails';   // название очереди
    public int    $maxAttempts = 3;          // попыток до failed()

    public function __construct(private readonly int $userId) {}

    public function handle(): void
    {
        $user = User::findOrFail($this->userId);
        // отправить email...
    }

    public function failed(\Throwable $e): void
    {
        // логировать или уведомить
    }
}
```

```bash
php sofy make:job SendWelcomeEmail
```

## Отправка в очередь

```php
use Sofy\Queue\Queue;
use Main\Jobs\SendWelcomeEmail;

// Немедленно
Queue::dispatch(new SendWelcomeEmail($user->id));

// С задержкой (секунды)
Queue::later(60, new SendWelcomeEmail($user->id));

// Или через метод задачи
(new SendWelcomeEmail($user->id))->dispatch();
(new SendWelcomeEmail($user->id))->dispatchLater(300);
```

## Воркер

```bash
php sofy queue:work                      # очередь default
php sofy queue:work --queue=emails       # конкретная очередь
php sofy queue:work --sleep=5            # пауза при пустой очереди (сек)
```

Воркер поддерживает graceful shutdown: при получении `SIGTERM`/`SIGINT` завершает текущую задачу и останавливается.

### Логика повтора

При ошибке задача повторяется с экспоненциальной задержкой:

| Попытка | Задержка |
|---------|---------|
| 1 | 5 с |
| 2 | 10 с |
| 3 | 20 с |

После `maxAttempts` вызывается `failed()` и задача переносится в `failed_jobs`.

## Конфигурация

```php
// config/queue.php
return [
    'default' => env('QUEUE_DRIVER', 'sync'),
];
```

```ini
# .env
QUEUE_DRIVER=database   # sync | database
```

Для `database`-драйвера требуется таблица `jobs` (миграция создаётся автоматически).

## Тестирование

```php
class SendEmailTest extends TestCase
{
    public function testEmailJobDispatched(): void
    {
        $queue = $this->fakeQueue();

        (new SendWelcomeEmail(1))->dispatch();

        $queue->assertDispatched(SendWelcomeEmail::class);
        $queue->assertDispatchedCount(1);
    }
}
```
