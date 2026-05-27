<?php

declare(strict_types=1);

namespace Sofy\Mail;

use Random\RandomException;
use RuntimeException;

class Mailer
{
    private string $to   = '';
    private string $name = '';

    public function to(string $address, string $name = ''): static
    {
        $clone       = clone $this;
        $clone->to   = $address;
        $clone->name = $name;
        return $clone;
    }

    /**
     * Отправляет письмо через PHP mail().
     *
     * @throws RuntimeException|RandomException
     */
    public function send(Mailable $mailable): void
    {
        $mailable->build();

        $to      = $this->name ? "$this->name <$this->to>" : $this->to;
        $subject = $mailable->getSubject();
        $html    = $mailable->getHtmlBody();
        $text    = $mailable->getTextBody();

        if ($this->to === '') {
            throw new RuntimeException('No recipient specified for mail.');
        }

        $from     = $mailable->getFromAddr() ?: (string) env('MAIL_FROM_ADDRESS', 'no-reply@localhost');
        $fromName = $mailable->getFromName()  ?: (string) env('MAIL_FROM_NAME', 'Sofy');

        $boundary = '=_sofy_' . bin2hex(random_bytes(8));

        $headers  = "From: $fromName <$from>\r\n";
        $headers .= "Reply-To: $from\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "X-Mailer: SofyPHP\r\n";

        if ($html !== '' && $text !== '') {
            $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
            $body  = "--$boundary\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n$text\r\n\r\n";
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n$html\r\n\r\n";
            $body .= "--$boundary--";
        } elseif ($html !== '') {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body = $html;
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body = $text;
        }

        if (!mail($to, $subject, $body, $headers)) {
            throw new RuntimeException("Failed to send email to $this->to.");
        }
    }
}
