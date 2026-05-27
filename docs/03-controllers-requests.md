# Контроллеры и запросы

## Контроллер

```php
// Main/Controllers/UserController.php
namespace Main\Controllers;

use Sofy\Http\Request;
use Sofy\Http\Response;
use Main\Models\User;

class UserController
{
    public function index(Request $request): Response
    {
        $users = User::all();
        return view('users.index', compact('users'));
    }

    public function show(Request $request): Response
    {
        $user = User::findOrFail((int) $request->route('id'));
        return view('users.show', compact('user'));
    }

    public function store(Request $request): Response
    {
        $data = $request->validate([
            'name'  => 'required|min:2|max:100',
            'email' => 'required|email|unique:users',
        ]);

        $user = User::create($data);
        return redirect('/users/' . $user->id);
    }
}
```

```bash
php sofy make:controller UserController
```

## Request

### Получение данных

```php
$request->get('key', 'default');      // из $_GET
$request->post('key', 'default');     // из $_POST
$request->input('key', 'default');    // из GET + POST + route params
$request->all();                       // все данные
$request->only('name', 'email');      // только указанные ключи
$request->except('password');         // все, кроме указанных
$request->has('key');                 // проверить наличие
$request->filled('key');              // не пустое значение
$request->json('key');                // тело JSON-запроса
$request->route('param');             // параметр маршрута
```

### Метаданные запроса

```php
$request->method();         // GET, POST, ...
$request->uri();            // /users?page=2
$request->path();           // /users
$request->ip();             // IP клиента
$request->userAgent();
$request->header('Accept');
$request->bearerToken();    // из Authorization: Bearer <token>
$request->isJson();
$request->isAjax();
$request->isMethod('POST');
$request->cookie('name');
```

### Файлы

```php
$file = $request->file('avatar');  // UploadedFile

if ($file && $file->isValid()) {
    $path = $file->store('avatars');        // в default disk
    $path = $file->storeAs('avatars', 'user-1.jpg');
    $path = $file->move(storage_path('uploads'));
    $ext  = $file->guessExtension();        // jpg, png, ...
}
```

## Response

```php
// HTML
return response('Hello', 200);
return response('<h1>Error</h1>', 500);

// Редирект
return redirect('/dashboard');
return redirect('/login', 302);

// JSON
return Response::json(['status' => 'ok']);
return Response::json($data, 201);

// View
return view('home', ['user' => $user]);
return Response::view('home', compact('user'), 200);

// Скачать файл
return Response::download(storage_path('files/report.pdf'));
return Response::download($path, 'report.pdf');

// Inline файл
return Response::file($path, 'image/jpeg');

// С заголовками
return response($content)->withHeader('X-Custom', 'value')->withStatus(201);
```

### Flash и redirect с данными

```php
return redirect('/users')
    ->with('success', 'Пользователь создан');

return redirect()->back()
    ->withErrors(['email' => 'Уже занят'])
    ->withInput($request->all());
```

В шаблоне:

```php
{{ old('email') }}          // старое значение
{{ session('success') }}    // flash-сообщение
```

## FormRequest

Валидация, вынесенная из контроллера:

```php
// Main/Requests/StoreUserRequest.php
namespace Main\Requests;

use Sofy\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'     => 'required|min:2|max:100',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Этот email уже зарегистрирован.',
        ];
    }
}
```

```php
// В контроллере — авто-валидация до вызова метода
public function store(StoreUserRequest $request): Response
{
    $data = $request->validated();
    User::create($data);
    // ...
}
```

```bash
php sofy make:request StoreUserRequest
```

## Валидация

### Прямо в контроллере

```php
$data = $request->validate([
    'name'  => 'required|min:2',
    'email' => 'required|email',
    'age'   => 'integer|min:18',
]);
```

### Доступные правила

| Правило | Описание |
|---------|---------|
| `required` | Обязательное поле |
| `string` | Строка |
| `integer` | Целое число |
| `numeric` | Число |
| `boolean` | Булево |
| `email` | Email |
| `url` | URL |
| `min:N` | Минимум N символов / N для числа |
| `max:N` | Максимум |
| `between:N,M` | Диапазон |
| `in:a,b,c` | Одно из значений |
| `not_in:...` | Не одно из |
| `unique:table,column` | Уникальность в БД |
| `exists:table,column` | Существование в БД |
| `confirmed` | Поле `field_confirmation` совпадает |
| `regex:/pattern/` | Регулярное выражение |
| `nullable` | Разрешить null |
| `date` | Дата |

### API-правила (Rule::class)

```php
use Sofy\Validation\Rule;

$data = $request->validate([
    'status' => ['required', Rule::in(['active', 'inactive'])],
    'role'   => ['required', Rule::exists('roles', 'slug')],
]);
```

### Обработка ошибок валидации

При ошибке бросает `ValidationException`. Фреймворк автоматически:
- Для веб: редирект назад с `errors` в сессии и `_old_input`
- Для JSON/AJAX: JSON `{errors: {...}, message: "..."}` с кодом 422

```sofy
@if(session('errors'))
    @foreach(session('errors') as $field => $message)
        <p class="error">{{ $message }}</p>
    @endforeach
@endif
```

## JSON Resources

Трансформация данных для API:

```php
// Main/Resources/UserResource.php
namespace Main\Resources;

use Sofy\Http\Resources\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id'    => $this->resource->id,
            'name'  => $this->resource->name,
            'email' => $this->resource->email,
        ];
    }
}

// Использование
return Response::json(new UserResource($user));
return Response::json(UserResource::collection($users));
```

```bash
php sofy make:resource UserResource
```
