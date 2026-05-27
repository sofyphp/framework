# Хранилище и утилиты

## Storage — файловое хранилище

### Базовое использование

```php
use Sofy\Storage\Storage;

// Запись / чтение
Storage::put('avatars/user-1.jpg', $contents);
$contents = Storage::get('avatars/user-1.jpg');

// Проверка
Storage::exists('avatars/user-1.jpg');   // bool
Storage::missing('avatars/user-1.jpg');  // bool

// Метаданные
Storage::size('file.pdf');               // bytes
Storage::lastModified('file.pdf');       // unix timestamp
Storage::mimeType('image.png');          // 'image/png'

// Операции
Storage::move('old/path.jpg', 'new/path.jpg');
Storage::copy('original.jpg', 'copy.jpg');
Storage::delete('file.jpg');
Storage::delete(['file1.jpg', 'file2.jpg']);

// Директории
Storage::files('avatars/');             // ['avatars/user-1.jpg', ...]
Storage::directories('uploads/');
Storage::makeDirectory('cache/images');
Storage::deleteDirectory('temp/');

// URL публичного файла
Storage::url('avatars/user-1.jpg');     // через 'public' диск
```

### Диски

```php
Storage::disk('local')->put('path', $data);
Storage::disk('public')->url('path');
Storage::disk('s3')->put('path', $data);
```

### Конфигурация

```php
// config/storage.php
return [
    'default' => env('STORAGE_DISK', 'local'),
    'disks'   => [
        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
        ],
        'public' => [
            'driver' => 'local',
            'root'   => base_path('public/storage'),
            'url'    => env('APP_URL') . '/storage',
        ],
        's3' => [
            'driver' => 's3',
            'key'    => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
        ],
    ],
];
```

### Загрузка файлов

```php
$file = $request->file('avatar');  // UploadedFile|null

if ($file && $file->isValid()) {
    $path = $file->store('avatars');                    // в default disk
    $path = $file->storeAs('avatars', 'user-1.jpg');
    $path = $file->move(storage_path('uploads'));
    $ext  = $file->guessExtension();                    // jpg, png, ...
}
```

---

## HTTP-клиент

```php
use Sofy\Http\Client\Http;

// GET с query-параметрами
$response = Http::get('https://api.example.com/users', ['page' => 1]);

// POST
$response = Http::post('https://api.example.com/users', ['name' => 'Alice']);

// Цепочка конфигурации
$response = Http::baseUrl('https://api.example.com')
    ->withToken('my-token')
    ->withTimeout(10)
    ->asJson()
    ->post('/users', ['name' => 'Alice']);

// Basic Auth
$response = Http::withBasicAuth('user', 'password')
    ->get('https://api.example.com/data');

// Работа с ответом
$status = $response->status();          // int
$ok     = $response->successful();      // 2xx
$fail   = $response->failed();          // не 2xx
$body   = $response->body();            // string
$json   = $response->json();            // array
$header = $response->header('Content-Type');
```

### Хелпер

```php
$response = http()->baseUrl('https://api.example.com')->get('/users');
```

---

## Collection

```php
use Sofy\Support\Collection;

$users = collect($array);

$users->filter(fn($u) => $u['active'])
      ->map(fn($u) => $u['name'])
      ->first();

$users->where('role', 'admin')->values();
$users->pluck('email');
$users->groupBy('role');
$users->sortBy('name');
$users->count();
$users->sum('score');
$users->avg('score');
$users->min('age');
$users->max('age');
$users->contains(fn($u) => $u['id'] === 1);
$users->each(fn($u) => ...);
$users->toArray();
$users->chunk(10);   // разбить на части
```

---

## Str — строковые утилиты

```php
use Sofy\Support\Str;

Str::slug('Hello World');         // hello-world
Str::camel('user_name');          // userName
Str::snake('userName');           // user_name
Str::studly('user_name');         // UserName
Str::upper('hello');              // HELLO
Str::lower('HELLO');              // hello
Str::ucfirst('hello world');      // Hello world
Str::limit('long text...', 50);   // обрезать до N символов
Str::contains('hello world', 'world');
Str::startsWith('hello', 'he');
Str::endsWith('hello', 'lo');
Str::random(32);                  // случайная строка
Str::uuid();                      // UUID v4
Str::plural('post');              // posts
Str::singular('users');           // user

// Fluent API
str('hello world')->slug()->upper()->get();
```

---

## Безопасность

### Хэширование

```php
use Sofy\Security\Hash;

$hash = Hash::make('password');       // bcrypt
$ok   = Hash::check('password', $hash);
$ok   = Hash::needsRehash($hash);

// Хелпер
$hash = bcrypt('password');
```

### Шифрование

```php
use Sofy\Security\Crypt;

$encrypted = Crypt::encrypt($value);     // AES-256-CBC + HMAC
$original  = Crypt::decrypt($encrypted);

// Хелперы
$enc = encrypt($value);
$dec = decrypt($enc);
```

Требует `APP_KEY` в `.env`.

---

## Локализация

### Файлы переводов

```php
// lang/en/messages.php
return [
    'welcome' => 'Welcome, :name!',
    'auth'    => [
        'failed' => 'Invalid credentials.',
    ],
];
```

### Использование

```php
trans('messages.welcome', ['name' => 'Alice']);   // Welcome, Alice!
__('messages.auth.failed');                        // Invalid credentials.

trans('auth.failed');      // из lang/en/auth.php
```

Язык по умолчанию определяется `config('app.locale')` (или `APP_LOCALE` в `.env`).

---

## Логирование

```php
use Sofy\Log\Log;

Log::debug('Debug message', ['context' => 'value']);
Log::info('User logged in', ['user_id' => 1]);
Log::warning('Low disk space');
Log::error('Something failed', ['exception' => $e->getMessage()]);
Log::critical('Service down');

// Хелпер
logger('User registered', ['id' => $user->id]);
logger('Error', ['msg' => $e->getMessage()], 'error');
```

Логи пишутся в `storage/logs/sofy.log`.

---

## Глобальные хелперы

| Хелпер | Описание |
|--------|---------|
| `app()` | Экземпляр Application |
| `app(SomeClass::class)` | Resolve из DI-контейнера |
| `env('KEY', 'default')` | Переменная окружения |
| `config('app.debug')` | Значение конфига |
| `base_path('file.php')` | Путь от корня проекта |
| `storage_path('logs')` | Путь до storage/ |
| `view('template', $data)` | Response с шаблоном |
| `redirect('/url')` | Redirect Response |
| `response('text', 200)` | Произвольный Response |
| `json_response($data)` | JSON Response |
| `route('name', $params)` | URL именованного маршрута |
| `asset('css/app.css')` | URL публичного ресурса |
| `session('key')` | Прочитать из сессии |
| `old('email')` | Старое значение из формы |
| `csrf_token()` | CSRF-токен |
| `csrf_field()` | `<input name="_token">` |
| `auth()` | Текущий пользователь |
| `can('ability')` | Проверка прав |
| `event($event)` | Отправить событие |
| `listen('event', $cb)` | Подписаться на событие |
| `broadcast($ch, $ev)` | WebSocket-вещание |
| `cache('key')` | Чтение из кэша |
| `now()` | Текущее время `Y-m-d H:i:s` |
| `collect($array)` | Создать Collection |
| `str('text')` | Fluent Str |
| `bcrypt($password)` | Bcrypt-хэш |
| `encrypt($value)` | Зашифровать |
| `decrypt($payload)` | Расшифровать |
| `abort(404)` | HttpException |
| `abort_if($cond, 403)` | Условный abort |
| `abort_unless($ok, 403)` | Условный abort |
| `trans('key')` | Перевод |
| `__('key')` | Псевдоним trans() |
| `logger('msg')` | Записать в лог |
| `redis()` | RedisClient |
| `signed_url('route')` | Подписанный URL |
| `http()` | HttpClient |
| `e($value)` | htmlspecialchars |
