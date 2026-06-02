# Админ-панель

Sofy идёт со встроенной админкой, доступной по `/admin`. Это не модуль и не
пакет — она часть ядра, всегда доступна, не требует включения. Модули
расширяют её через статический фасад `Sofy\Admin\Admin`.

## Карта URL

```text
/admin                            ← дашборд (стоковые виджеты + от модулей)
/admin/users                      ← список пользователей
/admin/system                     ← APP / PHP / OS / расширения
/admin/system/modules             ← автообнаруженные модули
/admin/system/update              ← апдейтер фреймворка + release notes
/admin/database                   ← список таблиц с числом строк
/admin/database/table/{name}      ← колонки + первые 100 строк
/admin/database/sql               ← raw SQL-консоль (read + write)
/admin/<твой-модуль>              ← что зарегистрируют модули
```

---

## Регистрация пунктов меню

Из любого места, где доступен фасад — обычно `Module::register()`:

```php
use Sofy\Admin\Admin;
use Sofy\Core\Application;
use Sofy\Core\Module;
use Sofy\View\Icons;

class Blog extends Module
{
    public function register(Application $app): void
    {
        Admin::menu()->add('blog.posts', 'Посты', '/admin/blog/posts')
            ->icon(Icons::FILE)                                  // SVG из каталога
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
| `->icon(Icons::FILE)` | SVG из `Sofy\View\Icons` (107 иконок); можно передать любой `<svg>`-литерал |
| `->section('Контент')` | раздел в сайдбаре; одинаковые секции группируются |
| `->order(10)` | сортировка внутри секции (по возрастанию) |
| `->badge($value)` | строка или замыкание — рендерится справа от лейбла |
| `->visibleIf($callback)` | пункт скрывается, если колбэк вернул `false` |

Модулю достаточно вызвать `Admin::menu()->add(...)` — больше ничего не нужно,
сайдбар перестроится автоматически.

---

## Маршруты модуля

Регистрируются обычным `$router` в твоём `routes.php`:

```php
$router->group(['prefix' => 'admin/blog'], function (Router $r) {
    $r->get('/posts',           [Blog\Admin\PostsController::class, 'index']);
    $r->get('/posts/{id}/edit', [Blog\Admin\PostsController::class, 'edit']);
    $r->post('/posts/{id}',     [Blog\Admin\PostsController::class, 'update']);
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
текущему URL, переключатель темы/локали уже в верхней панели.

---

## Виджеты дашборда

Виджет — класс, наследник `Sofy\Admin\AdminWidget`, с полями `$order` (сортировка)
и `$cols` (1 / 2 / 4 — четверть, половина, полный ряд).

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

Дашборд уважает `$cols`: подряд идущие виджеты одного размера упаковываются
в один `UI::grid(N, …)` — `cols=1` → 4 в ряд, `cols=2` → 2 в ряд, `cols=4` →
во всю ширину отдельной строкой. Порядок регистрации сохраняется.

### Стоковые виджеты

Регистрируются автоматически в `src/Admin/admin-routes.php` — свежий
`/admin` уже населён ими:

| Виджет | `cols` | Что показывает |
|---|---|---|
| `WelcomeWidget`        | 4 | Hero — `Sofy vX.Y.Z` + дата/время |
| `UsersCountWidget`     | 1 | Всего пользователей + delta за 7 дней |
| `DatabaseStatsWidget`  | 1 | Кол-во таблиц + чип драйвера |
| `ModulesCountWidget`   | 1 | Загруженные модули + первый по имени |
| `PhpRuntimeWidget`     | 1 | `PHP_VERSION` + SAPI + peak memory |
| `QuickActionsWidget`   | 4 | Сетка иконок-кнопок к ключевым разделам |
| `SystemHealthWidget`   | 2 | KV-пара: Sofy / PHP / OS / Memory / время |

Свой виджет с `$order < 0` встанет над `WelcomeWidget`. Замены по ключу нет —
если стоковый не нужен, легче не регистрировать его в форке `admin-routes.php`
или скрыть через `visibleIf` на уровне меню.

---

## Системные страницы

### `/admin/system` — Overview

Четыре stat-плитки (Sofy / PHP / APP_ENV / Memory) + KV-карточки
**Application** (`APP_NAME`, `APP_URL`, `APP_ENV`, `APP_DEBUG`, `Base path`,
`Modules`, `Routes`) и **PHP & extensions** (версия, SAPI, OS, server, memory
limit, max execution time, opcache, driver БД, список расширений).

### `/admin/system/modules`

Таблица всех модулей, обнаруженных `ModuleLoader::modules()` под `modules/`,
с их классом и путём.

### `/admin/database`

Список таблиц с числом строк. Драйверо-зависимая часть инкапсулирована в
`Sofy\Database\Schema\Grammar::listTablesSql()` — работает одинаково на
MySQL / PostgreSQL / SQLite. Клик по таблице → колонки + первые 100 строк.

### `/admin/database/sql` — SQL-консоль

Поле `<textarea>` + кнопка Run. `SELECT`/`SHOW`/`EXPLAIN`/`PRAGMA`/`WITH`/
`DESCRIBE` рендерят `UI::dataTable` с результатом (до 500 строк), всё
остальное считает `rows affected`. **Это прямой доступ к БД — relax только в
dev**, на проде закрывай ролью.

### `/admin/system/update` — Обновления

- Banner со статусом: `Up to date` / `Update available` / `Offline check`
- Четыре stat-плитки: установленная версия / последняя на Packagist / число
  релизов / PHP
- Кнопка **Update now** запускает `php sofy update --no-migrate` от лица
  web-юзера, после чего рендерит stdout в тёмном `<pre>` и инвалидирует кэш
- Под кнопкой — фид release notes: бэйджи `installed` / `newer` / `older`,
  кнопка **Refresh release notes** в хедере

#### Где брать описания

Контроллер ищет release notes в двух местах, в этом порядке:

1. **GitHub Releases API** — `https://api.github.com/repos/sofyphp/framework/releases`
   (кэш 30 мин). Чтобы описание появилось — пушни тэг и создай GitHub Release
   с body. Поддерживается markdown (заголовки, списки, code, **bold**, *italic*,
   `[ссылки](url)`).
2. **Локальный `CHANGELOG.md`** в корне проекта — fallback, когда GitHub
   Releases не созданы или API недоступен. Парсер ищет секции, начинающиеся
   с `## vX.Y.Z`, всё под секцией становится телом релиза.

То есть «добавить описание к версии» = либо отредактировать `CHANGELOG.md`
и нажать **Refresh release notes**, либо сделать GitHub Release. Кнопка
**Update now** сама сбросит кэш после установки новой версии.

---

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

Для быстрой выдачи учётки админа есть CLI:

```bash
php sofy admin:create               # интерактивно
php sofy admin:create --email=… --password=… --name=…
```

---

## Кастомизация

```php
\Sofy\Admin\Admin::brand('Моя <span>Компания</span>');     // лого в сайдбаре
\Sofy\Admin\Admin::panel()->loginUrl  = '/login';          // путь редиректа
\Sofy\Admin\Admin::panel()->logoutUrl = '/logout';
```

---

## Где что лежит

```
src/Admin/
  Admin.php                          ← фасад
  AdminPanel.php                     ← синглтон-реестр
  MenuItem.php                       ← DTO пункта меню
  AdminWidget.php                    ← базовый класс виджета
  AdminPage.php                      ← рендер chrome (сайдбар + topbar)
  Icons.php                          ← алиас → Sofy\View\Icons (back-compat)
  Middleware/EnsureAdmin.php
  Controllers/DashboardController.php
  Controllers/UsersController.php
  Controllers/SystemController.php
  Controllers/DatabaseController.php
  Controllers/UpdateController.php
  Widgets/                           ← 7 стоковых виджетов
  admin-routes.php                   ← подключается Application::loadRoutes()
```

Расширять Sofy-админку не нужно подменой — добавляй свои пункты меню,
виджеты и маршруты из модулей, и они встанут рядом со стоковыми без правок
ядра.
