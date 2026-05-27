# Почта и уведомления

## Mailable — письма

### Создание

```php
// Main/Mail/WelcomeEmail.php
namespace Main\Mail;

use Sofy\Mail\Mailable;
use Main\Models\User;

class WelcomeEmail extends Mailable
{
    public function __construct(private readonly User $user) {}

    public function build(): static
    {
        return $this
            ->subject('Добро пожаловать!')
            ->from('noreply@example.com', 'My App')
            ->view('emails.welcome', ['user' => $this->user]);
            // или ->html('<h1>Hello</h1>')
            // или ->text('Hello')
    }
}
```

```bash
php sofy make:mail WelcomeEmail
```

### Отправка

```php
use Sofy\Mail\Mail;

Mail::to('user@example.com')->send(new WelcomeEmail($user));
Mail::to($user->email, 'Alice')->send(new WelcomeEmail($user));
```

### Шаблон письма `views/emails/welcome.sofy.php`

```sofy
<h1>Привет, {{ $user->name }}!</h1>
<p>Спасибо за регистрацию.</p>
```

### Конфигурация

```php
// config/mail.php
return [
    'host'     => env('MAIL_HOST', 'smtp.mailtrap.io'),
    'port'     => env('MAIL_PORT', 2525),
    'username' => env('MAIL_USERNAME'),
    'password' => env('MAIL_PASSWORD'),
    'from'     => env('MAIL_FROM', 'noreply@example.com'),
    'from_name' => env('MAIL_FROM_NAME', 'Sofy App'),
];
```

---

## Notification — уведомления

Уведомления объединяют несколько каналов доставки: mail, database, broadcast.

### Создание уведомления

```php
// Main/Notifications/InvoicePaid.php
namespace Main\Notifications;

use Sofy\Notification\Notification;

class InvoicePaid extends Notification
{
    public function __construct(private readonly int $invoiceId, private readonly float $amount) {}

    public function via(mixed $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(mixed $notifiable): array
    {
        return [
            'subject' => 'Счёт оплачен',
            'body'    => "Счёт #{$this->invoiceId} на сумму {$this->amount} оплачен.",
            'to'      => $notifiable->email,
        ];
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'amount'     => $this->amount,
        ];
    }

    public function toBroadcast(mixed $notifiable): array
    {
        return [
            'channel' => 'users.' . $notifiable->id,
            'event'   => 'InvoicePaid',
            'data'    => ['invoice_id' => $this->invoiceId],
        ];
    }
}
```

```bash
php sofy make:notification InvoicePaid
```

### Отправка

```php
use Sofy\Notification\Notifier;

// Через модель (требует трейт Notifiable)
$user->notify(new InvoicePaid($invoiceId, $amount));

// Статически
Notifier::send($user, new InvoicePaid($invoiceId, $amount));
```

### Трейт Notifiable

Добавьте к модели пользователя:

```php
use Sofy\Notification\Notifiable;

class User extends Model
{
    use Notifiable;
}
```

### Работа с уведомлениями в БД

Требуется таблица `notifications` (миграция `create_notifications_table`).

```php
// Все уведомления
$notifications = $user->notifications();

// Только непрочитанные
$unread = $user->unreadNotifications();

// Пометить все как прочитанные
$user->markNotificationsRead();
```

### Каналы

| Канал | Метод уведомления | Описание |
|-------|-------------------|---------|
| `mail` | `toMail($notifiable)` | SMTP через Mailer |
| `database` | `toDatabase($notifiable)` | Запись в таблицу `notifications` |
| `broadcast` | `toBroadcast($notifiable)` | Redis pub/sub |

---

## Тестирование почты

```php
public function testWelcomeEmailSent(): void
{
    $mail = $this->fakeMail();

    Mail::to('user@example.com')->send(new WelcomeEmail($user));

    $mail->assertSent(WelcomeEmail::class);
    $mail->assertSentTo('user@example.com', WelcomeEmail::class);
}
```
