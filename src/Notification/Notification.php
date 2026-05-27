<?php

declare(strict_types=1);

namespace Sofy\Notification;

/**
 * Base class for all notifications.
 *
 * Usage:
 *   class InvoicePaid extends Notification
 *   {
 *       public function __construct(private readonly Invoice $invoice) {}
 *
 *       public function via(mixed $notifiable): array
 *       {
 *           return ['mail', 'database'];
 *       }
 *
 *       public function toMail(mixed $notifiable): array
 *       {
 *           return [
 *               'subject' => 'Invoice Paid',
 *               'body'    => "Invoice #{$this->invoice->id} has been paid.",
 *           ];
 *       }
 *
 *       public function toDatabase(mixed $notifiable): array
 *       {
 *           return ['invoice_id' => $this->invoice->id, 'amount' => $this->invoice->amount];
 *       }
 *   }
 *
 * Then on a notifiable model:
 *   $user->notify(new InvoicePaid($invoice));
 */
abstract class Notification
{
    public string $id;

    public function __construct()
    {
        $this->id = bin2hex(random_bytes(16));
    }

    /** Return array of channel names: 'mail', 'database', 'broadcast'. */
    abstract public function via(mixed $notifiable): array;

    /** Called by MailChannel. Return ['subject'=>..., 'body'=>..., 'to'=>...]. */
    public function toMail(mixed $notifiable): array { return []; }

    /** Called by DatabaseChannel. Return any array to store as JSON. */
    public function toDatabase(mixed $notifiable): array { return []; }

    /** Called by BroadcastChannel. Return ['channel'=>..., 'event'=>..., 'data'=>...]. */
    public function toBroadcast(mixed $notifiable): array { return []; }
}
