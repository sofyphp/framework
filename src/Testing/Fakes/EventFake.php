<?php

declare(strict_types=1);

namespace Sofy\Testing\Fakes;

use Sofy\Events\Dispatcher;
use PHPUnit\Framework\Assert;

/**
 * Fake event dispatcher — captures fired events instead of dispatching.
 *
 * Usage:
 *   $fake = $this->fakeEvents();
 *   // ... trigger code that fires events ...
 *   $fake->assertDispatched(UserRegistered::class);
 *   $fake->assertDispatchedTimes(OrderPlaced::class, 2);
 *   $fake->assertNothingDispatched();
 */
class EventFake extends Dispatcher
{
    /** @var array<int, array{name: string, payload: array}> */
    private array $dispatched = [];

    private static ?self $instance = null;

    public static function swap(): static
    {
        $fake = new static();
        Dispatcher::setInstance($fake);
        return self::$instance = $fake;
    }

    public function dispatch(string|object $event, array $payload = []): array
    {
        $name = is_object($event) ? $event::class : $event;
        $this->dispatched[] = ['name' => $name, 'payload' => is_object($event) ? [$event] : $payload];
        return [];
    }

    public function assertDispatched(string $event, ?callable $callback = null): void
    {
        $matching = array_filter(
            $this->dispatched,
            fn($e) => $e['name'] === $event
                && ($callback === null || $callback(...$e['payload']))
        );
        Assert::assertNotEmpty(
            $matching,
            "Expected event [$event] to be dispatched, but it was not."
        );
    }

    public function assertNotDispatched(string $event): void
    {
        $matching = array_filter($this->dispatched, fn($e) => $e['name'] === $event);
        Assert::assertEmpty($matching, "Expected event [$event] NOT to be dispatched, but it was.");
    }

    public function assertDispatchedTimes(string $event, int $times): void
    {
        $count = count(array_filter($this->dispatched, fn($e) => $e['name'] === $event));
        Assert::assertSame($times, $count, "Expected event [$event] to be dispatched $times time(s), dispatched $count.");
    }

    public function assertNothingDispatched(): void
    {
        Assert::assertEmpty(
            $this->dispatched,
            'Expected no events to be dispatched, but ' . count($this->dispatched) . ' were.'
        );
    }

    public function dispatched(string $event): array
    {
        return array_values(array_filter($this->dispatched, fn($e) => $e['name'] === $event));
    }
}
