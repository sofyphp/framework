# Тестирование

Sofy поставляется с базовым классом `TestCase` поверх PHPUnit, HTTP-хелперами и Fakes для изоляции сторонних систем.

## Запуск

```bash
composer test            # = phpunit --no-coverage
vendor/bin/phpunit       # напрямую
```

Конфиг — `phpunit.xml` (PHPUnit 11), бутстрап — `tests/bootstrap.php` (поднимает
`Application`, чтобы `config()`/`auth()`/`session()` резолвились). В репозитории
есть набор юнит-тестов ядра (`tests/Unit/*`): роутер, Auth, Crypt/Hash, поиск,
UI-компоненты, модели, мессенджер, уведомления и др. Базовый `Tests\TestCase`
даёт одноразовую in-memory SQLite через `freshDatabase()`:

```php
final class ProductTest extends \Tests\TestCase
{
    protected function setUp(): void
    {
        $db = $this->freshDatabase();
        $db->execute('CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT, ...)');
    }

    public function test_create(): void
    {
        $p = \Products\Models\Product::create(['name' => 'Widget', /* … */]);
        $this->assertSame('Widget', \Products\Models\Product::find((int) $p->id)->name);
    }
}
```

## TestCase

```php
// tests/Feature/UserTest.php
namespace Tests\Feature;

use Sofy\Testing\TestCase;

class UserTest extends TestCase
{
    public function testShowUser(): void
    {
        $response = $this->get('/users/1');

        $response->assertOk();
        $response->assertSee('Alice');
    }

    public function testCreateUser(): void
    {
        $response = $this->post('/users', [
            'name'  => 'Alice',
            'email' => 'alice@example.com',
        ]);

        $response->assertCreated();
        $response->assertJson(['name' => 'Alice']);
    }
}
```

### HTTP-методы

```php
$this->get('/uri', $headers);
$this->post('/uri', $data, $headers);
$this->put('/uri', $data, $headers);
$this->patch('/uri', $data, $headers);
$this->delete('/uri', $data, $headers);
```

---

## TestResponse

Все HTTP-методы возвращают `TestResponse`.

```php
// Статус
$response->assertStatus(200);
$response->assertOk();          // 200
$response->assertCreated();     // 201
$response->assertNoContent();   // 204
$response->assertNotFound();    // 404
$response->assertForbidden();   // 403
$response->assertUnauthorized(); // 401
$response->assertRedirect('/login');   // 3xx + Location header

// Тело
$response->assertSee('text');
$response->assertDontSee('text');

// JSON
$response->assertJson(['key' => 'value']);
$response->assertJsonPath('user.name', 'Alice');

// Заголовки
$response->assertHeader('Content-Type');
$response->assertHeader('X-Custom', 'value');

// Чтение данных
$response->getStatus();   // int
$response->getBody();     // string
$response->json();        // array (decoded)
```

### Цепочка

```php
$this->post('/users', ['name' => 'Alice', 'email' => 'a@b.com'])
    ->assertCreated()
    ->assertJson(['name' => 'Alice'])
    ->assertHeader('Location');
```

---

## Fakes

### fakeQueue

```php
public function testJobDispatched(): void
{
    $queue = $this->fakeQueue();

    (new SendWelcomeEmail($userId))->dispatch();

    $queue->assertPushed(SendWelcomeEmail::class);
    $queue->assertPushedOn('emails', SendWelcomeEmail::class);
    $queue->assertPushedCount(1);
    $queue->assertNothingPushed();   // провалит, если что-то есть

    // Фильтрация по данным
    $queue->assertPushed(SendWelcomeEmail::class, fn($job) => $job->userId === $userId);

    // Получить все задачи нужного класса
    $jobs = $queue->pushed(SendWelcomeEmail::class);
}
```

### fakeMail

```php
public function testWelcomeEmailSent(): void
{
    $mail = $this->fakeMail();

    Mail::to('user@example.com')->send(new WelcomeEmail($user));

    $mail->assertSent(WelcomeEmail::class);
    $mail->assertSentTo('user@example.com', WelcomeEmail::class);
    $mail->assertNotSent(InvoiceEmail::class);
    $mail->assertNothingSent();
}
```

### fakeEvents

```php
public function testEventFired(): void
{
    $events = $this->fakeEvents();

    event(new UserRegistered($user));

    $events->assertDispatched(UserRegistered::class);
    $events->assertDispatchedCount(1);
    $events->assertNotDispatched(OrderShipped::class);
}
```

### fakeStorage

```php
public function testFileUploaded(): void
{
    $storage = $this->fakeStorage();

    // Код, который сохраняет файл
    $response = $this->post('/upload', [...]);

    $storage->assertExists('avatars/user-1.jpg');
    $storage->assertMissing('avatars/user-99.jpg');
    $storage->assertCount('avatars', 1);
}
```

---

## Unit-тесты

```php
// tests/Unit/UserTest.php
namespace Tests\Unit;

use Sofy\Testing\TestCase;
use Main\Models\User;

class UserTest extends TestCase
{
    public function testFullName(): void
    {
        $user = new User(['first_name' => 'Alice', 'last_name' => 'Smith']);
        $this->assertSame('Alice Smith', $user->full_name);
    }
}
```

## Создание тестов

```bash
php sofy make:test UserTest              # tests/Feature/UserTest.php
php sofy make:test UserUnitTest --unit   # tests/Unit/UserUnitTest.php
```

## Запуск

```bash
./vendor/bin/phpunit
./vendor/bin/phpunit tests/Feature/UserTest.php
./vendor/bin/phpunit --filter testCreateUser
```
