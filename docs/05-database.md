# База данных

## ORM — Active Record

### Базовая модель

```php
// Main/Models/Post.php
namespace Main\Models;

use Sofy\Database\Model;

class Post extends Model
{
    protected static string $table      = 'posts';     // по умолчанию: snake_case + s
    protected static string $primaryKey = 'id';
    protected static bool   $timestamps = true;
    protected static bool   $softDeletes = false;

    protected static array $fillable = ['title', 'body', 'user_id'];
    protected static array $hidden   = ['secret'];
    protected static array $casts    = [
        'published_at' => 'datetime',
        'settings'     => 'array',
        'is_active'    => 'boolean',
    ];
}
```

### Автоматическое имя таблицы

`UserProfile` → `user_profiles`, `Post` → `posts`.

### Каст типов

| Тип | Значение |
|-----|---------|
| `int`, `integer` | `(int)` |
| `float`, `double` | `(float)` |
| `bool`, `boolean` | `(bool)` |
| `string` | `(string)` |
| `array`, `json` | `json_decode(..., true)` |
| `datetime` | `DateTimeImmutable` |

---

## CRUD

```php
// Создание
$post = Post::create(['title' => 'Hello', 'user_id' => 1]);

// Чтение
$post = Post::find(1);
$post = Post::findOrFail(1);      // RuntimeException если не найден
$all  = Post::all();

// Обновление
$post->title = 'Updated';
$post->save();

// Удаление
$post->delete();

// Первый или создать
$user = User::firstOrCreate(
    ['email' => 'a@b.com'],
    ['name'  => 'Alice']
);

// Обновить или создать
User::updateOrCreate(
    ['email' => 'a@b.com'],
    ['name'  => 'Alice Updated']
);
```

---

## Query Builder

```php
Post::where('published', true)
    ->where('user_id', $userId)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

Post::where('views', '>', 100)->first();

Post::where('status', 'draft')->count();
Post::where('status', 'active')->sum('views');
Post::where('status', 'active')->avg('rating');

// whereIn / whereNotIn
Post::whereIn('status', ['draft', 'published'])->get();

// whereNull / whereNotNull
Post::whereNull('deleted_at')->get();
Post::whereNotNull('published_at')->get();

// whereLike
Post::whereLike('title', '%php%')->get();

// orWhere
Post::where('status', 'active')->orWhere('featured', true)->get();

// Несколько условий в одном where
Post::where(['status' => 'active', 'user_id' => 1])->get();

// Raw выражения
use Sofy\Database\RawExpression;
Post::where(new RawExpression('YEAR(created_at)'), 2024)->get();

// Пагинация
$paginator = Post::where('status', 'active')
    ->orderBy('created_at', 'desc')
    ->paginate(15);     // → Paginator

$posts    = $paginator->items();
$total    = $paginator->total();
$lastPage = $paginator->lastPage();
$links    = $paginator->links();    // HTML-ссылки
```

---

## Отношения

### hasOne / hasMany / belongsTo

```php
class User extends Model
{
    public function posts(): \Sofy\Database\Relations\HasMany
    {
        return $this->hasMany(Post::class, 'user_id', 'id');
    }

    public function profile(): \Sofy\Database\Relations\HasOne
    {
        return $this->hasOne(Profile::class, 'user_id');
    }
}

class Post extends Model
{
    public function user(): \Sofy\Database\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

### belongsToMany

```php
class Post extends Model
{
    public function tags(): \Sofy\Database\BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag', 'post_id', 'tag_id');
    }
}

// Использование
$post->tags()->attach([1, 2, 3]);
$post->tags()->detach([2]);
$post->tags()->sync([1, 3]);
```

### hasManyThrough

```php
class Country extends Model
{
    public function posts(): \Sofy\Database\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Post::class, User::class, 'country_id', 'user_id');
    }
}
```

### Ленивая загрузка

```php
$user = User::find(1);
foreach ($user->posts as $post) {   // ← вызов через __get
    echo $post->title;
}
```

### Жадная загрузка (N+1 prevention)

```php
// Один запрос для users + один для всех их posts
$users = User::with('posts', 'profile')->get();

foreach ($users as $user) {
    foreach ($user->posts as $post) { ... }   // нет доп. запросов
}
```

### Аксессоры и мутаторы

```php
class User extends Model
{
    // $user->full_name
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    // $user->password = '...' → bcrypt hash
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_BCRYPT);
    }
}
```

### Soft Deletes

```php
class Post extends Model
{
    protected static bool $softDeletes = true;
}

$post->delete();                  // устанавливает deleted_at
Post::withTrashed()->get();       // все, включая удалённые
Post::onlyTrashed()->get();       // только удалённые
$post->restore();                 // восстановить
$post->forceDelete();             // физическое удаление
```

### Наблюдатели

```php
// Main/Observers/UserObserver.php
class UserObserver
{
    public function creating(User $user): void { ... }
    public function created(User $user): void  { ... }
    public function updating(User $user): void { ... }
    public function updated(User $user): void  { ... }
    public function deleting(User $user): void { ... }
    public function deleted(User $user): void  { ... }
    public function saving(User $user): void   { ... }
    public function saved(User $user): void    { ... }
}

// Регистрация в Model::boot() или AppServiceProvider
User::observe(UserObserver::class);
```

---

## Query Builder (DB::table)

Для запросов без модели:

```php
use Sofy\Database\DB;

DB::table('users')->where('active', 1)->get();
DB::table('logs')->insert(['message' => 'test', 'level' => 'info']);
DB::table('users')->where('id', 1)->update(['name' => 'Bob']);
DB::table('sessions')->where('expired_at', '<', now())->delete();

// Произвольный SQL
DB::statement('TRUNCATE TABLE cache');
DB::select('SELECT * FROM users WHERE id = ?', [1]);

// Транзакции
DB::transaction(function () {
    DB::table('accounts')->where('id', 1)->decrement('balance', 100);
    DB::table('accounts')->where('id', 2)->increment('balance', 100);
});
```

---

## Миграции

```php
// database/migrations/2024_01_01_000000_create_posts_table.php
return new class {
    public function up(): void
    {
        \Sofy\Database\Schema\Schema::create('posts', function ($table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->boolean('featured')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->unique('slug');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        \Sofy\Database\Schema\Schema::dropIfExists('posts');
    }
};
```

### Типы колонок

| Метод | SQL |
|-------|-----|
| `id()` | `BIGINT UNSIGNED AUTO_INCREMENT` |
| `string($name, $length = 255)` | `VARCHAR(N)` |
| `char($name, $length = 100)` | `CHAR(N)` |
| `text($name)` | `TEXT` |
| `longText($name)` | `LONGTEXT` |
| `integer($name)` | `INT` |
| `bigInteger($name)` | `BIGINT` |
| `unsignedBigInteger($name)` | `BIGINT UNSIGNED` |
| `tinyInteger($name)` | `TINYINT` |
| `smallInteger($name)` | `SMALLINT` |
| `float($name)` | `FLOAT` |
| `decimal($name, $precision, $scale)` | `DECIMAL(P,S)` |
| `boolean($name)` | `TINYINT(1)` |
| `datetime($name)` | `DATETIME` |
| `timestamp($name)` | `TIMESTAMP` |
| `date($name)` | `DATE` |
| `time($name)` | `TIME` |
| `json($name)` | `JSON` |
| `uuid($name)` | `CHAR(36)` |
| `enum($name, $values)` | `ENUM(...)` |
| `timestamps()` | `created_at + updated_at DATETIME NULL` |
| `softDeletes()` | `deleted_at DATETIME NULL` |
| `rememberToken()` | `remember_token VARCHAR(100) NULL` |

### Модификаторы колонки

```php
$table->string('email')->unique();
$table->string('phone')->nullable();
$table->integer('views')->default(0);
$table->unsignedBigInteger('parent_id')->index();
```

### Составной первичный ключ

```php
$table->primary(['user_id', 'post_id']);
```

### Команды миграций

```bash
php sofy migrate              # запустить новые
php sofy migrate:rollback     # откат последнего батча
php sofy migrate:rollback --steps=3
php sofy migrate:fresh        # drop all + migrate
php sofy migrate:status       # статус всех миграций
php sofy make:migration create_posts_table
```

---

## Сидеры

```php
// database/seeds/DatabaseSeeder.php
class DatabaseSeeder extends \Sofy\Database\Seeder
{
    public function run(): void
    {
        $this->call(UserSeeder::class);
        $this->call(PostSeeder::class);
    }
}

// database/seeds/UserSeeder.php
class UserSeeder extends \Sofy\Database\Seeder
{
    public function run(): void
    {
        $this->disableForeignKeys();
        $this->truncate('users');
        $this->enableForeignKeys();

        UserFactory::new()->count(20)->create();
    }
}
```

```bash
php sofy db:seed
php sofy db:seed --class=UserSeeder
```

---

## Фабрики

```php
// Main/Factories/UserFactory.php
namespace Main\Factories;

use Sofy\Database\Factory;
use Main\Models\User;

class UserFactory extends Factory
{
    protected string $model = User::class;

    public function definition(): array
    {
        return [
            'name'     => 'User ' . rand(1, 9999),
            'email'    => 'user' . rand(1, 9999) . '@example.com',
            'password' => password_hash('secret', PASSWORD_BCRYPT),
        ];
    }
}

// Использование
UserFactory::new()->create();
UserFactory::new()->count(5)->create();
UserFactory::new()->state(['role' => 'admin'])->create();
UserFactory::new()->make(['name' => 'Alice']);   // без сохранения
```

```bash
php sofy make:factory UserFactory
```
