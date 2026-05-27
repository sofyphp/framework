# Роутинг

Маршруты регистрируются в `routes/web.php` и `routes/api.php`.

## Базовые маршруты

```php
// routes/web.php
$router->get('/', [HomeController::class, 'index']);
$router->get('/users/{id}', [UserController::class, 'show']);
$router->post('/users', [UserController::class, 'store']);
$router->put('/users/{id}', [UserController::class, 'update']);
$router->patch('/users/{id}', [UserController::class, 'update']);
$router->delete('/users/{id}', [UserController::class, 'destroy']);
$router->options('/users', fn() => response('', 200));

// Замыкание
$router->get('/ping', fn() => response('pong'));

// Любой метод
$router->any('/webhook', [WebhookController::class, 'handle']);

// Конкретные методы
$router->match(['GET', 'POST'], '/form', [FormController::class, 'handle']);
```

## Параметры маршрута

```php
// {param} — обязательный параметр
$router->get('/posts/{id}', fn(Request $req) => Post::findOrFail($req->route('id')));

// :param — альтернативный синтаксис
$router->get('/users/:slug', [UserController::class, 'bySlug']);
```

Параметры доступны через `$request->route('param')`.

## Именованные маршруты

```php
$router->get('/users/{id}', [UserController::class, 'show'])->name('users.show');

// Генерация URL
$url = route('users.show', ['id' => 42]);  // → /users/42
```

## Middleware

```php
use Sofy\Http\Middleware\AuthMiddleware;
use Sofy\Http\Middleware\ThrottleMiddleware;

// На маршруте
$router->get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(AuthMiddleware::class);

// Несколько
$router->post('/api/data', [DataController::class, 'store'])
    ->middleware([AuthMiddleware::class, new ThrottleMiddleware(60, 1)]);
```

## Группы маршрутов

```php
// Префикс и middleware
$router->group(['prefix' => 'admin', 'middleware' => [AuthMiddleware::class]], function($router) {
    $router->get('/dashboard', [AdminController::class, 'dashboard']);
    $router->get('/users', [AdminController::class, 'users']);
});

// Только префикс
$router->group(['prefix' => 'v2'], function($router) {
    $router->get('/users', [V2UserController::class, 'index']);
});
```

## API-маршруты

```php
// routes/api.php — автоматически префикс /api
$router->api(function($router) {
    $router->get('/users', [UserApiController::class, 'index']);    // GET /api/users
    $router->post('/users', [UserApiController::class, 'store']);   // POST /api/users
});
```

## Resource-маршруты

```php
// Создаёт 7 маршрутов: index, create, store, show, edit, update, destroy
$router->resource('posts', PostController::class);

// API Resource (без create и edit)
$router->apiResource('posts', PostApiController::class);
```

Сгенерированные маршруты для `$router->resource('posts', PostController::class)`:

| Метод | URI | Действие |
|-------|-----|---------|
| GET | /posts | index |
| GET | /posts/create | create |
| POST | /posts | store |
| GET | /posts/{id} | show |
| GET | /posts/{id}/edit | edit |
| PUT/PATCH | /posts/{id} | update |
| DELETE | /posts/{id} | destroy |

## Список маршрутов

```bash
php sofy route:list
```

## Кэш маршрутов

```bash
php sofy route:cache   # создаёт bootstrap/cache/routes.php
php sofy route:clear   # очищает кэш
```
