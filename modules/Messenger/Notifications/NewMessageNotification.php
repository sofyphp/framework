<?php

declare(strict_types=1);

namespace Messenger\Notifications;

use Sofy\Notification\Notification;

/**
 * Stored for each recipient when a chat message arrives. Its toDatabase()
 * shape (title/body/url/tag) is exactly what the core notifications feed
 * exposes to sofyNotify — so recipients get a desktop notification with sound
 * anywhere in the admin, and clicking it opens the conversation.
 */
class NewMessageNotification extends Notification
{
    public function __construct(
        private readonly string $senderName,
        private readonly string $preview,
        private readonly int $channelId,
    ) {
        parent::__construct();
    }

    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title' => 'Сообщение от ' . $this->senderName,
            'body'  => $this->preview,
            'url'   => '/admin/messages/' . $this->channelId,
            'tag'   => 'chat-' . $this->channelId,
        ];
    }
}
