# Кэш и сессии

## Cache

Драйвер выбирается по конфигу `cache.default` (`.env`: `CACHE_DRIVER`).

Доступные драйверы: `file`, `redis`, `array`.

```php
use Sofy\Cache\Cache;

Cache::set('key', 'value');             // без TTL
Cache::set('key', 'value', 3600);       // TTL в секундах

Cache::get('key');                      // mixed
Cache::get('key', 'default');           // со значением по умолчанию

Cache::has('key');                      // bool
Cache::forget('key');                   // удалить ключ
Cache::flush();                         // очистить весь кэш

Cache::increment('counter');            // +1
Cache::increment('counter', 5);         // +5
Cache::decrement('counter');

// Получить или вычислить
$users = Cache::remember('all-users', 600, fn() => User::all());
```

### Переключение хранилища

```php
Cache::store('redis')->get('key');
Cache::store('array')->set('temp', $value, 60);
```

### L1 memory layer

Повторные `Cache::get()` в одном запросе не обращаются к хранилищу — данные берутся из in-memory слоя.

### Конфигурация

```php
// config/cache.php
return [
    'default' => env('CACHE_DRIVER', 'file'),
    'drivers' => [
        'file'  => ['path' => storage_path('cache')],
        'redis' => ['prefix' => 'sofy:'],
        'array' => [],
    ],
];
```

---

## Session

Сессия стартует автоматически через middleware. Прямой доступ через `session()` хелпер или объект `Session`.

```php
use Sofy\Http\Session;

$session = new Session();
$session->start();

// Запись / чтение
$session->set('user_id', 42);
$session->get('user_id');
$session->get('key', 'default');

// Проверка и удаление
$session->has('key');
$session->forget('key');
$session->pull('key');          // прочитать и удалить

// Все данные
$session->all();

// Очистить
$session->flush();

// Сгенерировать новый ID (защита от session fixation)
$session->regenerate();
```

### Flash-сообщения

Flash-данные живут только до следующего запроса.

```php
// Запись
$session->flash('success', 'Пользователь создан');
$session->flash('errors', ['email' => 'Уже занят']);

// Чтение (и автоматическое удаление)
$session->getFlash('success');
```

### CSRF-токен

```php
$token = $session->token();   // генерируется автоматически, хранится в '_token'
```

В шаблоне: `@csrf` вставляет скрытый `<input name="_token" value="...">`.

### Конфигурация

```php
// config/session.php
return [
    'driver'   => env('SESSION_DRIVER', 'file'),   // file | redis
    'lifetime' => 120,                              // минуты
    'prefix'   => 'sofy_sess:',
];
```

Для Redis-сессий в `.env`:

```ini
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### Хелпер session()

```php
session('key');                   // read
session('key', 'default');        // read with default
session(['key' => 'value']);      // write (array)
```
