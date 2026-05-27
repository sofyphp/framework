<?php

declare(strict_types=1);

namespace Sofy\Testing\Fakes;

use Sofy\Mail\Mailable;
use Sofy\Mail\Mailer;
use PHPUnit\Framework\Assert;

/**
 * Fake mailer — captures sent mail instead of actually sending.
 *
 * Usage:
 *   $fake = $this->fakeMail();
 *   // ... trigger code that sends mail ...
 *   $fake->assertSent(InvoicePaidMail::class);
 *   $fake->assertSentTo('user@example.com', InvoicePaidMail::class);
 *   $fake->assertNothingSent();
 */
class MailFake extends Mailer
{
    /** @var array<int, array{to: string, mailable: Mailable}> */
    private array $sent = [];

    private static ?self $instance = null;

    public static function swap(): static
    {
        return self::$instance = new static();
    }

    public static function restore(): void
    {
        self::$instance = null;
    }

    public function send(Mailable $mailable): void
    {
        $mailable->build();
        $this->sent[] = ['to' => $this->getRecipient(), 'mailable' => $mailable];
    }

    private function getRecipient(): string
    {
        // Access via reflection since $to is private in parent
        $ref = new \ReflectionProperty(Mailer::class, 'to');
        return (string) $ref->getValue($this);
    }

    public function assertSent(string $mailableClass, ?callable $callback = null): void
    {
        $matching = array_filter(
            $this->sent,
            fn($s) => $s['mailable'] instanceof $mailableClass
                && ($callback === null || $callback($s['mailable']))
        );
        Assert::assertNotEmpty(
            $matching,
            "Expected [$mailableClass] to be sent, but it was not."
        );
    }

    public function assertNotSent(string $mailableClass): void
    {
        $matching = array_filter($this->sent, fn($s) => $s['mailable'] instanceof $mailableClass);
        Assert::assertEmpty($matching, "Expected [$mailableClass] NOT to be sent, but it was.");
    }

    public function assertSentTo(string $address, string $mailableClass): void
    {
        $matching = array_filter(
            $this->sent,
            fn($s) => $s['to'] === $address && $s['mailable'] instanceof $mailableClass
        );
        Assert::assertNotEmpty(
            $matching,
            "Expected [$mailableClass] to be sent to [$address], but it was not."
        );
    }

    public function assertNothingSent(): void
    {
        Assert::assertEmpty($this->sent, 'Expected no mail to be sent, but ' . count($this->sent) . ' were sent.');
    }

    public function assertSentCount(int $count): void
    {
        Assert::assertCount($count, $this->sent, "Expected $count mail(s) to be sent.");
    }

    public function sent(): array { return $this->sent; }
}
