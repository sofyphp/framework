# Модули

Модули позволяют разделить приложение на самодостаточные единицы со своими роутами, сервисами, командами, конфигом и миграциями. Новый модуль подключается простым размещением папки в `modules/` — больше ничего редактировать не нужно.

## Структура модуля

```
modules/
  Blog/
    Blog.php          ← главный класс (extends Module)
    config.php        ← возвращает array конфига
    routes.php        ← файл роутов ($router в scope)
    Controllers/
    Models/
    Commands/
    Views/
    Migrations/
```

## Создание модуля

```bash
php sofy make:module Blog
composer dump-autoload
```

После `dump-autoload` модуль обнаруживается автоматически.

## Главный класс

```php
// modules/Blog/Blog.php
namespace Blog;

use Sofy\Core\Application;
use Sofy\Core\Module;

class Blog extends Module
{
    public function name(): string
    {
        return 'blog';   // ключ конфига: config('blog.*')
    }

    public function config(): array
    {
        return require $this->path('config.php');
    }

    // Регистрация сервисов в DI-контейнер (до boot)
    public function register(Application $app): void
    {
        $app->singleton(PostRepository::class, fn() => new PostRepository());
    }

    // Подписка на события, регистрация наблюдателей
    public function boot(Application $app): void
    {
        \Main\Models\Post::observe(PostObserver::class);
    }

    // Консольные команды модуля
    public function commands(): array
    {
        return [
            \Blog\Commands\ImportPostsCommand::class,
        ];
    }

    // Хук установки (php sofy module:install Blog)
    public function install(Application $app): void
    {
        // seed initial data, create directories, publish assets
    }
}
```

## Роуты модуля

```php
// modules/Blog/routes.php
use Sofy\Http\Router;

/** @var Router $router */

// Веб-роуты
$router->web(function (Router $router): void {
    $router->get('/blog', [\Blog\Controllers\PostController::class, 'index']);
    $router->get('/blog/{slug}', [\Blog\Controllers\PostController::class, 'show']);
});

// API-роуты (автопрефикс /api)
$router->api(function (Router $router): void {
    $router->get('/blog', [\Blog\Controllers\PostApiController::class, 'index']);
});
```

## Конфигурация модуля

```php
// modules/Blog/config.php
return [
    'per_page'    => 10,
    'cache_ttl'   => 3600,
    'image_path'  => 'blog/images',
];
```

Доступно через `config('blog.per_page')`.

## Путь к файлам модуля

```php
// Внутри класса модуля
$this->path();                  // /abs/path/to/modules/Blog
$this->path('Views/index.php'); // /abs/path/to/modules/Blog/Views/index.php
$this->path('Migrations');      // /abs/path/to/modules/Blog/Migrations
```

## Жизненный цикл

```
bootstrap/app.php
  → $app->loadModules()
      → ModuleLoader::discover('modules/')
      → Module::register($app)   ← bind/singleton в контейнер + mergeConfig
  → $app->boot()
      → bootDatabase()
      → loadRoutes() (app web.php, api.php)
      → ModuleLoader::loadRoutes($router)   ← Module::routes($router)
      → ModuleLoader::bootAll($app)         ← Module::boot($app)

sofy CLI
  → bootForConsole()
      → ModuleLoader::bootAll($app)
  → foreach module->commands() → kernel->register()
```

## Команды для модулей

```bash
php sofy make:module Blog          # создать новый модуль
php sofy module:install Blog       # вызвать Blog::install()
```

## Пример: Demo-модуль

```php
// modules/Demo/Demo.php
namespace Demo;

use Sofy\Core\Module;

class Demo extends Module
{
    public function name(): string { return 'demo'; }

    public function config(): array
    {
        return ['greeting' => 'Hello from Demo!', 'version' => '1.0'];
    }
}
```

```php
// modules/Demo/routes.php
$router->web(function ($router) {
    $router->get('/demo', fn() => config('demo.greeting'));
});

$router->api(function ($router) {
    $router->get('/demo', fn() => ['greeting' => config('demo.greeting')]);
});
```

Открыть `/demo` — вернёт `Hello from Demo!`.  
Открыть `/api/demo` — вернёт `{"greeting":"Hello from Demo!"}`.

## Миграции модуля

Команда `migrate` автоматически сканирует `modules/*/Migrations/` в дополнение к `database/migrations/`.

```
modules/Blog/Migrations/
  2024_06_01_000000_create_posts_table.php
  2024_06_01_000001_create_categories_table.php
```

## Шаблоны модуля

Шаблоны модуля регистрируются с namespace-префиксом `{name}::`:

```sofy
@include('blog::partials.post-card', ['post' => $post])
{{ view('blog::index', compact('posts')) }}
```

## Автозагрузка

После создания нового модуля нужно один раз выполнить:

```bash
composer dump-autoload
```

Убедитесь, что `Modules\\` прописан в `composer.json`:

```json
{
    "autoload": {
        "psr-4": {
            "Sofy\\": "src/",
            "Main\\": "Main/",
            "Modules\\": "modules/"
        }
    }
}
```
