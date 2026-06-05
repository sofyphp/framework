# Аутентификация и авторизация

## Auth

```php
use Sofy\Auth\Auth;

// Войти по паролю
$ok = Auth::attempt(['email' => $email, 'password' => $password]);

// Войти с известным объектом
Auth::login($user);

// Выйти
Auth::logout();

// Проверки
Auth::check();      // bool — вошёл?
Auth::guest();      // bool — гость?
Auth::id();         // int|null
Auth::user();       // Main\Models\User|null

// Авторизация
Auth::can('edit-post', $post);   // bool
```

Пользователь по умолчанию — `Main\Models\User`. Можно передать другой класс вторым аргументом `attempt()`.

Те же методы доступны через хелпер `auth()`:

```php
auth()->check();   auth()->user();   auth()->id();   auth()->logout();
```

### Встроенный логин в админку

С `requireAuth = true` (включён по умолчанию) `/admin` закрыт. Фреймворк
поставляет готовую форму входа:

- `GET /admin/login` — страница логина (собрана из UI-компонентов).
- `POST /admin/login` — `Auth::attempt()` + проверка роли + редирект на `?next`.
- `POST /admin/logout` — выход (кнопка есть в сайдбаре админки).

Защищено глобальным CSRF, есть троттлинг (5 попыток / IP+email / 15 мин). Чтобы
зайти — создай админа и открой `/admin`:

```bash
php sofy admin:create
```

Переопределить можно своим маршрутом `/admin/login` в `routes/web.php` (грузится
раньше) или отключить гейт: `\Sofy\Admin\Admin::panel()->requireAuth = false;`.
Подробнее про админку — [Админ-панель](15-admin.md).

### Пример контроллера входа

```php
public function login(Request $request): Response
{
    $data = $request->validate([
        'email'    => 'required|email',
        'password' => 'required',
    ]);

    if (!Auth::attempt($data)) {
        return redirect('/login')->withErrors(['email' => 'Неверные данные']);
    }

    return redirect('/dashboard');
}

public function logout(Request $request): Response
{
    Auth::logout();
    return redirect('/');
}
```

---

## Gate — авторизация

### Регистрация прав

Обычно в `bootstrap/app.php` или сервис-провайдере:

```php
use Sofy\Auth\Gate;

Gate::define('edit-post', fn($user, $post) => $user->id === $post->user_id);
Gate::define('delete-post', fn($user, $post) => $user->isAdmin() || $user->id === $post->user_id);

// Super-admin — before hook (вернуть null для продолжения стандартной проверки)
Gate::define('before', function($user, $ability) {
    if ($user->isAdmin()) return true;
    return null;
});
```

### Проверки

```php
Gate::allows('edit-post', $post);        // bool
Gate::denies('edit-post', $post);        // bool
Gate::any(['edit-post', 'delete-post'], $post);   // хотя бы одно
Gate::none(['ban-user', 'delete-user'], $user);   // ни одного

Gate::authorize('edit-post', $post);     // throws HttpException 403

// Как текущий другой пользователь
Gate::forUser($admin)->allows('delete-post', $post);
```

### В контроллере

```php
public function edit(Request $request): Response
{
    $post = Post::findOrFail((int) $request->route('id'));
    Gate::authorize('edit-post', $post);
    // ...
}
```

---

## Policy

Политики группируют логику авторизации для конкретной модели.

```php
// Main/Policies/PostPolicy.php
namespace Main\Policies;

class PostPolicy
{
    public function view(mixed $user, mixed $post): bool
    {
        return true;    // все могут смотреть
    }

    public function edit(mixed $user, mixed $post): bool
    {
        return $user->id === $post->user_id;
    }

    public function delete(mixed $user, mixed $post): bool
    {
        return $user->isAdmin() || $user->id === $post->user_id;
    }
}
```

```php
// Регистрация
Gate::policy(Post::class, PostPolicy::class);

// Использование (автоматически подхватывается Gate::allows)
Gate::allows('edit', $post);
Gate::authorize('delete', $post);
Auth::can('view', $post);
```

```bash
php sofy make:policy PostPolicy
```

---

## Роли (RBAC)

Добавьте трейт к модели пользователя:

```php
use Sofy\Auth\HasRoles;

class User extends Model
{
    use HasRoles;
}
```

Требуются таблицы `roles` и `role_user` (создаются в миграции `create_roles_table`).

```php
// Назначение
$user->assignRole('admin');
$user->assignRole('moderator');

// Снять
$user->removeRole('moderator');

// Синхронизация (удаляет все роли, затем назначает новые)
$user->syncRoles(['editor', 'author']);

// Проверки
$user->hasRole('admin');                        // bool
$user->hasRole(['admin', 'moderator']);         // любая из списка
$user->hasAnyRole(['admin', 'moderator']);      // любая
$user->hasAllRoles(['editor', 'author']);       // все

// Получить все роли пользователя
$roles = $user->roles();   // array
```

### Gate + роли

```php
Gate::define('manage-users', fn($user) => $user->hasRole('admin'));
```

---

## API-токены (HasApiTokens)

```php
use Sofy\Auth\HasApiTokens;

class User extends Model
{
    use HasApiTokens;
}
```

Требуется таблица `personal_access_tokens`.

```php
// Создать токен (показывается один раз)
$token = $user->createToken('mobile-app');
$plainText = $token->plainTextToken;    // отдать клиенту

// С ограниченными правами
$token = $user->createToken('read-only', ['read']);

// Проверить право токена
$user->tokenCan('read');
$user->tokenCan('*');   // все права

// Список токенов
$user->tokens();

// Отозвать все
$user->revokeAllTokens();
```

### Аутентификация через Bearer-токен

Заголовок запроса: `Authorization: Bearer {id}|{token}`

```php
// routes/api.php
use Sofy\Auth\HasApiTokens;

$router->api(function($router) {
    $router->get('/user', function(Request $req) {
        $bearer = $req->bearerToken();
        $user   = \Main\Models\User::findByToken($bearer);
        if (!$user) return Response::json(['error' => 'Unauthorized'], 401);
        return Response::json(['id' => $user->id, 'name' => $user->name]);
    });
});
```

---

## Сброс пароля

```php
use Sofy\Auth\PasswordBroker;

// Создать токен и сохранить в password_reset_tokens
$token = PasswordBroker::createToken('user@example.com');

// В ссылке письма: /password/reset?token={$token}&email=...

// Проверить токен (TTL 60 минут)
if (!PasswordBroker::validate('user@example.com', $token)) {
    return redirect('/forgot-password')->withErrors(['token' => 'Ссылка устарела']);
}

// Сбросить пароль
PasswordBroker::reset('user@example.com', $token, function($user) use ($request) {
    $user->password = $request->post('password');
    $user->save();
});
```

---

## Верификация email

Добавьте интерфейс и трейт к модели:

```php
use Sofy\Auth\MustVerifyEmail;
use Sofy\Auth\IsVerifiable;

class User extends Model implements MustVerifyEmail
{
    use IsVerifiable;
}
```

```php
$user->hasVerifiedEmail();                   // bool
$user->markEmailAsVerified();                // устанавливает email_verified_at
$user->sendEmailVerificationNotification();  // отправляет уведомление
$url = $user->verificationUrl();             // ссылка для подтверждения
```

---

## Middleware

```php
use Sofy\Http\Middleware\AuthMiddleware;
use Sofy\Http\Middleware\GuestMiddleware;

// Только для аутентифицированных
$router->get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(AuthMiddleware::class);

// Только для гостей
$router->get('/login', [AuthController::class, 'form'])
    ->middleware(GuestMiddleware::class);
```

- `AuthMiddleware` — редирект на `/login` (веб) или 401 JSON (AJAX/API)
- `GuestMiddleware` — редирект на `/dashboard` если уже вошёл

---

## Глобальные хелперы

```php
auth()           // Auth::user() — текущий пользователь
can('ability', $model)   // Gate::allows(...)
```

### В шаблонах

```sofy
@auth
    Привет, {{ auth()->name }}
@endauth

@guest
    <a href="/login">Войти</a>
@endguest

@can('edit', $post)
    <a href="/posts/{{ $post->id }}/edit">Редактировать</a>
@endcan
```
