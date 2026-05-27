# UI-компоненты

Страницы в Sofy создаются исключительно через `UI` — PHP-объекты, которые рендерятся в готовый HTML с встроенными стилями.

```php
use Sofy\View\UI;
```

---

## Страница

Точка входа — `UI::page()`. Возвращает объект `Page`, который можно наполнить компонентами и вернуть как `Response`.

```php
return UI::page('Пользователи')
    ->nav('MyApp', [
        '/dashboard' => 'Dashboard',
        '/users'     => 'Users',
    ])
    ->header('Все пользователи', UI::button('+ Новый', '/users/create', 'primary'))
    ->add(
        UI::grid(3, [
            UI::stat('Всего',    1240, '+5%'),
            UI::stat('Активных', 983,  '+2%'),
            UI::stat('Забанено', 12,   '-1%'),
        ]),
        UI::card('Список',
            UI::table(
                ['ID', 'Имя', 'Email', 'Статус', ''],
                $users,
                ['id', 'name', 'email',
                    fn($r) => UI::badge($r['status'], $r['status'] === 'active' ? 'success' : 'danger'),
                    fn($r) => UI::button('Ред.', "/users/{$r['id']}/edit", 'ghost', 'sm'),
                ]
            )
        ),
    )
    ->response();
```

### Page API

```php
UI::page(string $title): Page

->nav(string $brand, array $links = [], mixed $actions = null): Page
->header(string $title, mixed $actions = null): Page
->add(mixed ...$components): Page
->css(string $extraCss): Page     // дополнительный CSS
->footer(bool $show): Page        // показать/скрыть подвал
->moon(bool $show): Page          // декоративная луна
->themeToggle(): Page             // кнопка тёмной/светлой темы
->withHtmx(): Page                // подключить HTMX с CDN
->response(int $status = 200): Response
->render(): string
```

---

## Layout

### Карточка

```php
UI::card('Заголовок', $content)
UI::card(null, $content)           // без заголовка
```

### Сетка

```php
UI::grid(3, [$comp1, $comp2, $comp3])   // 1 | 2 | 3 | 4 колонки
```

### Вкладки

```php
UI::tabs([
    'Профиль' => $profileCard,
    'Настройки' => $settingsCard,
], default: 0)
```

### Sidebar layout

```php
UI::sidebarLayout(
    sidebar:  $nav,
    content:  $mainContent,
    width:    '240px',
    position: 'left',   // left | right
)
```

### Прокрутка

```php
UI::scrollArea($content, height: '320px', direction: 'vertical')
// direction: vertical | horizontal | both
```

---

## Данные

### Таблица

```php
UI::table(
    headers: ['ID', 'Имя', 'Email', 'Роль', ''],
    rows:    $users,
    cols:    [
        'id',
        'name',
        'email',
        fn($r) => UI::badge($r['role']),
        fn($r) => UI::button('Edit', "/users/{$r['id']}/edit", 'ghost', 'sm'),
    ],
    empty:   'Нет пользователей',
)
```

### Таблица с сортировкой и поиском

```php
UI::dataTable(
    headers:    ['ID', 'Имя', 'Email'],
    rows:       $users,
    cols:       ['id', 'name', 'email'],
    perPage:    15,
    searchable: true,
    nosort:     [4],   // индексы колонок без сортировки
)
```

### Статистика

```php
UI::stat('Доход', '$12,400', '+8.3%', 'за месяц')
```

### Ключ-значение

```php
UI::kv([
    'Статус'   => UI::badge('active', 'success'),
    'Создан'   => $user->created_at,
    'Email'    => $user->email,
], layout: 'inline')   // inline | stacked
```

### Прогресс

```php
UI::progress(72, 'Заполнение профиля', variant: 'success', size: 'md', showPct: true)
// variant: accent | success | warning | danger | info
// size: sm | md | lg
```

### Чарт

```php
UI::chart(
    data:   ['Янв' => 120, 'Фев' => 145, 'Мар' => 98, 'Апр' => 160],
    type:   'bar',    // bar | line | pie | donut
    height: 200,
    label:  'Продажи',
)
```

### Хронология

```php
UI::timeline([
    ['title' => 'Регистрация',    'time' => '10:00', 'variant' => 'success'],
    ['title' => 'Первый заказ',   'time' => '11:30', 'content' => 'Заказ #1042'],
    ['title' => 'Оплата прошла',  'time' => '11:31', 'variant' => 'accent'],
])
```

### Аккордеон

```php
UI::accordion([
    ['title' => 'Вопрос 1', 'content' => 'Ответ...', 'open' => true],
    ['title' => 'Вопрос 2', 'content' => $card],
])
```

---

## Формы

```php
UI::form('/users', 'POST')
    ->input('Имя', 'name', required: true)
    ->email('Email', 'email', required: true)
    ->password('Пароль', 'password')
    ->number('Возраст', 'age')
    ->select('Роль', 'role', ['admin' => 'Admin', 'user' => 'User'], selected: 'user')
    ->radio('Тип', 'type', ['free' => 'Free', 'pro' => 'Pro'], selected: 'free')
    ->textarea('Bio', 'bio', rows: 5)
    ->checkbox('Активен', 'is_active', checked: true)
    ->toggle('Уведомления', 'notify')
    ->file('Аватар', 'avatar', accept: 'image/*')
    ->hidden('redirect', '/dashboard')
    ->cols(fn($f) => $f->input('Имя', 'first_name')->input('Фамилия', 'last_name'))
    ->withErrors($errors)   // ['field' => 'message']
    ->submit('Сохранить')
```

`UI::form()` автоматически добавляет CSRF-токен и `_method` при PUT/PATCH/DELETE.

---

## Навигация

### Хлебные крошки

```php
UI::breadcrumb([
    'Главная'    => '/',
    'Пользователи' => '/users',
    'Alice'      => null,    // текущая страница — без ссылки
])
```

### Пагинация

```php
UI::pagination(current: 3, total: 12, baseUrl: '?page=', window: 2)
```

### Шаги

```php
UI::steps(['Регистрация', 'Профиль', 'Оплата', 'Готово'], current: 2)
```

---

## Обратная связь

### Алерт

```php
UI::alert('Пользователь сохранён', type: 'success', title: 'Готово')
// type: success | warning | danger | info
```

### Тост

```php
UI::toast('Сохранено', type: 'success', title: null, dismissAfter: 4)
// dismissAfter: 0 = только вручную
```

### Пустое состояние

```php
UI::emptyState(
    title:       'Нет публикаций',
    description: 'Создайте первую публикацию',
    action:      UI::button('+ Создать', '/posts/create', 'primary'),
    icon:        '✦',
)
```

---

## Контент

```php
UI::heading('Заголовок раздела', level: 2)
UI::text('Обычный текст', muted: false)
UI::code('SELECT * FROM users', language: 'sql')
UI::divider()
UI::ul(['Пункт 1', 'Пункт 2'])
UI::ol(['Шаг 1', 'Шаг 2'])
UI::raw('<strong>Доверенный HTML</strong>')   // только для доверенных данных
```

### Герой

```php
UI::hero('Добро пожаловать в Sofy', 'Минималистичный PHP 8.3 фреймворк')
    ->actions(
        UI::button('Начать', '/docs', 'primary', 'lg'),
        UI::button('GitHub', 'https://github.com', 'ghost', 'lg'),
    )
```

---

## Аватар и бейдж

```php
UI::avatar('Alice Smith', variant: 'accent', size: 'md', src: '/avatars/1.jpg')
// variant: accent | success | warning | danger | info | muted
// size: sm | md | lg | xl

UI::badge('active', 'success')
// variant: default | success | warning | danger | info | accent
```

---

## Кнопки

```php
UI::button('Сохранить', '/save', variant: 'primary', size: 'md')
UI::button('Удалить', '/delete', variant: 'danger', size: 'sm', method: 'DELETE', confirm: 'Вы уверены?')
UI::deleteButton('/users/1/delete')   // кнопка удаления с подтверждением

// variant: primary | ghost | warning | danger | success
// size: sm | md | lg
```

---

## Наложения

### Модальное окно

```php
// Кнопка открытия
Modal::trigger('confirm-modal', 'Открыть')

// Само окно
UI::modal(
    id:      'confirm-modal',
    title:   'Подтверждение',
    content: UI::text('Вы уверены?'),
    footer:  UI::button('Да', '#', 'danger'),
    size:    'md',    // sm | md | lg | xl
)
```

### Drawer (панель)

```php
Drawer::trigger('filters-drawer', 'Фильтры')

UI::drawer(
    id:       'filters-drawer',
    title:    'Фильтры',
    content:  $filterForm,
    footer:   UI::button('Применить', '#', 'primary'),
    position: 'right',   // right | left
    width:    '380px',
)
```

### Тултип

```php
UI::tooltip(UI::badge('?', 'info'), 'Подсказка', placement: 'top')
// placement: top | bottom | left | right
```

---

## Служебные

### Спиннер

```php
UI::spinner(size: 'md', variant: 'accent')
// size: sm | md | lg
// variant: accent | muted | white
```

### Command Palette (⌘K)

Разместить один раз на странице:

```php
UI::commandPalette([
    ['label' => 'Пользователи',    'url' => '/users',    'category' => 'Навигация', 'shortcut' => '⌘U'],
    ['label' => 'Настройки',       'url' => '/settings', 'category' => 'Навигация'],
    ['label' => 'Создать заказ',   'url' => '/orders/create', 'icon' => '+'],
], placeholder: 'Поиск…')
```

Открывается по `⌘K` / `Ctrl+K`.

### Debug Bar

Показывать только при `APP_DEBUG=true`:

```php
if (config('app.debug')) {
    $page->add(UI::debugBar());
}
```

---

## Полный пример контроллера

```php
namespace Main\Controllers;

use Sofy\Http\Request;
use Sofy\Http\Response;
use Sofy\View\UI;
use Main\Models\User;

class UserController
{
    public function index(Request $request): Response
    {
        $users = User::all();

        return UI::page('Пользователи')
            ->nav('Sofy<span>App</span>', [
                '/dashboard' => 'Dashboard',
                '/users'     => 'Users',
            ])
            ->header('Пользователи', UI::button('+ Новый', '/users/create', 'primary'))
            ->add(
                UI::dataTable(
                    headers: ['ID', 'Имя', 'Email', 'Роль', ''],
                    rows:    $users,
                    cols:    [
                        'id', 'name', 'email',
                        fn($u) => UI::badge($u['role'] ?? 'user'),
                        fn($u) => implode('', [
                            UI::button('Ред.',  "/users/{$u['id']}/edit",   'ghost', 'sm'),
                            UI::deleteButton("/users/{$u['id']}", 'Удал.'),
                        ]),
                    ],
                )
            )
            ->response();
    }

    public function create(Request $request): Response
    {
        return UI::page('Новый пользователь')
            ->nav('SofyApp', ['/users' => 'Назад'])
            ->header('Создать пользователя')
            ->add(
                UI::card('Данные',
                    UI::form('/users', 'POST')
                        ->input('Имя', 'name', required: true)
                        ->email('Email', 'email', required: true)
                        ->password('Пароль', 'password', required: true)
                        ->select('Роль', 'role', ['user' => 'User', 'admin' => 'Admin'])
                        ->submit('Создать')
                )
            )
            ->response();
    }
}
```
