# События и вещание

## События (Events)

### Создание события

```php
// Main/Events/UserRegistered.php
namespace Main\Events;

use Sofy\Events\Event;
use Main\Models\User;

class UserRegistered extends Event
{
    public function __construct(public readonly User $user) {}
}
```

### Регистрация слушателей

```php
use Sofy\Events\Dispatcher;

$dispatcher = Dispatcher::getInstance();

// Строковое имя события
$dispatcher->listen('user.registered', function(array $data) {
    // обработать
});

// Типизированное событие
$dispatcher->listen(UserRegistered::class, function(UserRegistered $event) {
    // $event->user
});

// Wildcard
$dispatcher->listen('order.*', function() {
    // любое событие с префиксом order.
});

// Один раз
$dispatcher->listenOnce('app.booted', fn() => ...);
```

### Отправка событий

```php
use Sofy\Events\Dispatcher;

// Строковое
Dispatcher::getInstance()->dispatch('user.registered', ['user' => $user]);

// Объект
Dispatcher::getInstance()->dispatch(new UserRegistered($user));

// Хелпер
event(new UserRegistered($user));
event('user.registered', ['user' => $user]);
```

### Остановка цепочки

```php
class UserRegistered extends Event
{
    public function __construct(public readonly User $user) {}
}

$dispatcher->listen(UserRegistered::class, function(UserRegistered $e) {
    if ($e->user->isBanned()) {
        $e->stopPropagation();
    }
});
```

### Удаление слушателей

```php
$dispatcher->forget('user.registered');             // все слушатели события
$dispatcher->forget('user.registered', $listener); // конкретный
$dispatcher->forgetAll();
```

---

## Broadcasting (реалтайм)

Публикует события через Redis pub/sub для доставки в браузер (через WebSocket или SSE).

### Настройка

```ini
# .env
BROADCAST_DRIVER=redis   # redis | log | null
```

### Событие с вещанием

```php
// Main/Events/OrderShipped.php
namespace Main\Events;

use Sofy\Broadcasting\ShouldBroadcast;

class OrderShipped implements ShouldBroadcast
{
    public function __construct(private readonly int $orderId) {}

    public function broadcastOn(): string
    {
        return 'orders.' . $this->orderId;   // канал
    }

    public function broadcastAs(): string
    {
        return 'OrderShipped';               // имя события на клиенте
    }

    public function broadcastWith(): array
    {
        return ['order_id' => $this->orderId];   // данные
    }
}
```

```php
use Sofy\Broadcasting\Broadcaster;

// Через объект события
Broadcaster::event(new OrderShipped($order->id));

// Напрямую
Broadcaster::broadcast('orders.42', 'OrderShipped', ['order_id' => 42]);

// Хелпер
broadcast('orders.42', 'OrderShipped', ['order_id' => 42]);
```

### JavaScript-клиент (SSE / WebSocket)

```javascript
// SSE
const source = new EventSource('/broadcasting/subscribe?channel=orders.42');
source.addEventListener('OrderShipped', e => {
    const data = JSON.parse(e.data);
    console.log('Order shipped:', data.order_id);
});
```

### Тестирование

```php
public function testEventDispatched(): void
{
    $events = $this->fakeEvents();

    event(new UserRegistered($user));

    $events->assertDispatched(UserRegistered::class);
    $events->assertDispatchedCount(1);
    $events->assertNotDispatched(OrderShipped::class);
}
```

```bash
php sofy make:event UserRegistered
```
