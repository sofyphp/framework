<?php

declare(strict_types=1);

namespace Sofy\Mail;

use Sofy\View\View;

abstract class Mailable
{
    protected string $subject  = '';
    protected string $fromAddr = '';
    protected string $fromName = '';
    protected string $htmlBody = '';
    protected string $textBody = '';

    abstract public function build(): static;

    // ── Builder API ───────────────────────────────────────────────────────────

    protected function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    protected function from(string $email, string $name = ''): static
    {
        $this->fromAddr = $email;
        $this->fromName = $name;
        return $this;
    }

    /** Рендерит шаблон и устанавливает как HTML-тело письма. */
    protected function view(string $template, array $data = []): static
    {
        $this->htmlBody = View::make($template, $data);
        return $this;
    }

    protected function html(string $html): static
    {
        $this->htmlBody = $html;
        return $this;
    }

    protected function text(string $text): static
    {
        $this->textBody = $text;
        return $this;
    }

    // ── Getters (used by Mailer) ──────────────────────────────────────────────

    public function getSubject(): string  { return $this->subject; }
    public function getFromAddr(): string { return $this->fromAddr; }
    public function getFromName(): string { return $this->fromName; }
    public function getHtmlBody(): string { return $this->htmlBody; }
    public function getTextBody(): string { return $this->textBody; }
}
