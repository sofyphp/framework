<?php

declare(strict_types=1);

namespace Sofy\Mail;

/**
 * Mail::to('user@example.com')->send(new WelcomeMail($user));
 * Mail::to('user@example.com', 'John')->send(new InvoiceMail($invoice));
 */
class Mail
{
    public static function to(string $address, string $name = ''): Mailer
    {
        return (new Mailer())->to($address, $name);
    }
}
