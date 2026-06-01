# Админ-панель

Sofy идёт со встроенной админкой, доступной по `/admin`. Это не модуль и не
пакет — она часть ядра, всегда доступна, не требует включения. Модули
расширяют её через статический фасад `Admin`.

```text
/admin                ← дашборд (виджеты от модулей)
/admin/users          ← стоковый список пользователей
/admin/<твоё>         ← всё, что зарегистрируют модули или routes/web.php
```

## Регистрация пунктов меню

Из любого места, где доступен фасад `Sofy\Admin\Admin` — обычно
`Module::register()`:

```php
use Sofy\Admin\Admin;
use Sofy\Core\Application;
use Sofy\Core\Module;

class Blog extends Module
{
    public function register(Application $app): void
    {
        Admin::menu()->add('blog.posts', 'Посты', '/admin/blog/posts')
            ->icon('📝')
            ->section('Контент')
            ->order(10)
            ->badge(fn () => \Blog\Post::draft()->count())
            ->visibleIf(fn () => auth()->user()?->hasRole('editor'));
    }
}
```

| API | Что делает |
|---|---|
| `Admin::menu()->add($key, $label, $url)` | возвращает `MenuItem` (`$key` — стабильный идентификатор для replace-by-key) |
| `->icon('📝')` | эмодзи/символ слева от лейбла |
| `->section('Контент')` | раздел в сайдбаре; одинаковые секции группируются |
| `->order(10)` | сортировка внутри секции (по возрастанию) |
| `->badge($value)` | строка или замыкание — рендерится справа от лейбла |
| `->visibleIf($callback)` | пункт скрывается, если колбэк вернул `false` |

Модулю достаточно вызвать `Admin::menu()->add(...)` — больше ничего не нужно.

## Маршруты модуля

Регистрируются обычным `$router` в твоём `routes.php`:

```php
$router->group(['prefix' => 'admin/blog'], function (Router $r) {
    $r->get('/posts',            [Blog\Admin\PostsController::class, 'index']);
    $r->get('/posts/{id}/edit',  [Blog\Admin\PostsController::class, 'edit']);
    $r->post('/posts/{id}',      [Blog\Admin\PostsController::class, 'update']);
});
```

В контроллере возвращай страницу через `Admin::page()`:

```php
return Admin::page('Посты')
    ->header('Все посты', UI::button('+ Новый', '/admin/blog/posts/create', 'primary'))
    ->add(
        UI::card(null, UI::dataTable($headers, $rows)),
    )
    ->response();
```

`Admin::page()` оборачивает контент в стандартный chrome — сайдбар автоматически
строится из всех зарегистрированных пунктов, активная подсветка считается по
текущему URL, тема/локаль-переключатель уже в верхней панели.

## Виджеты дашборда

```php
use Sofy\Admin\Admin;
use Sofy\Admin\AdminWidget;
use Sofy\View\UI;

class UsersCountWidget extends AdminWidget
{
    public int $order = 10;
    public int $cols  = 1;

    public function render(): mixed
    {
        return UI::stat('Пользователей', \Main\Models\User::count(), '+12%');
    }
}

// В Module::register():
Admin::widget(UsersCountWidget::class);
```

Дашборд рендерит виджеты в `UI::grid(4, …)` — 1/2/3/4 колонки в зависимости
от `cols`. На мобильном схлопывается в одну колонку.

## Аутентификация

По умолчанию `/admin` открыт — удобно для разработки. Чтобы включить защиту,
позови один раз (например, в `bootstrap/app.php`):

```php
\Sofy\Admin\Admin::useAuth('admin');
```

После этого middleware `EnsureAdmin`:
1. Пропускает `loginUrl`/`logoutUrl` без проверки.
2. Редиректит неавторизованных на `Admin::panel()->loginUrl` (по умолчанию
   `/admin/login`) с параметром `?next=`.
3. На авторизованных без указанной роли отдаёт `403`.

Сам логин-форму ты пишешь — у Sofy уже есть `Auth` / `HasRoles` / `Gate`,
просто отрисуй простую форму на любом подходящем URL и сделай
`Auth::login($user)`.

## Кастомизация

```php
\Sofy\Admin\Admin::brand('Моя <span>Компания</span>');     // лого в сайдбаре
\Sofy\Admin\Admin::panel()->loginUrl  = '/login';          // путь редиректа
\Sofy\Admin\Admin::panel()->logoutUrl = '/logout';
```

## Где что лежит

```
src/Admin/
  Admin.php                          ← фасад
  AdminPanel.php                     ← синглтон-реестр
  MenuItem.php                       ← DTO пункта меню
  AdminWidget.php                    ← базовый класс виджета
  AdminPage.php                      ← рендер chrome (сайдбар + topbar)
  Middleware/EnsureAdmin.php
  Controllers/DashboardController.php
  Controllers/UsersController.php
  admin-routes.php                   ← подключается Application::loadRoutes()
```

Расширять Sofy-админку не нужно подменой — добавляй свои пункты меню и
маршруты из модулей, и они встанут рядом со стоковыми без правок ядра.
