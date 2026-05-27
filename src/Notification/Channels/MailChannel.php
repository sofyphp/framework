<?php

declare(strict_types=1);

namespace Sofy\Notification\Channels;

use Sofy\Mail\Mailable;
use Sofy\Mail\Mailer;
use Sofy\Notification\Notification;
use Sofy\Notification\NotificationChannel;

class MailChannel implements NotificationChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        $data = $notification->toMail($notifiable);
        if (empty($data)) {
            return;
        }

        $to = $data['to'] ?? (
            method_exists($notifiable, 'routeNotificationForMail')
                ? $notifiable->routeNotificationForMail()
                : ($notifiable->email ?? null)
        );

        if (!$to) {
            return;
        }

        $subject = $data['subject'] ?? 'Notification';
        $body    = $data['body']    ?? '';
        $html    = $data['html']    ?? '';

        $mailable = new class($subject, $body, $html) extends Mailable {
            public function __construct(
                private readonly string $sub,
                private readonly string $txt,
                private readonly string $htm,
            ) {}

            public function build(): static
            {
                $this->subject($this->sub);
                if ($this->htm !== '') {
                    $this->html($this->htm);
                }
                if ($this->txt !== '') {
                    $this->text($this->txt);
                }
                return $this;
            }
        };

        (new Mailer())->to($to)->send($mailable);
    }
}
