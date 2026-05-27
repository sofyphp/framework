<?php

declare(strict_types=1);

namespace Sofy\Testing\Fakes;

use Sofy\Queue\Job;
use Sofy\Queue\Queue;
use Sofy\Queue\Drivers\DriverInterface;
use PHPUnit\Framework\Assert;

/**
 * Fake queue driver — captures dispatched jobs instead of storing them.
 *
 * Usage:
 *   $fake = $this->fakeQueue();
 *   // ... trigger code that dispatches jobs ...
 *   $fake->assertPushed(SendWelcomeEmail::class);
 *   $fake->assertPushedOn('high', ProcessPayment::class);
 *   $fake->assertNothingPushed();
 */
class QueueFake implements DriverInterface
{
    /** @var array<int, array{queue: string, job: Job}> */
    private array $jobs = [];

    public static function swap(): static
    {
        $fake = new static();
        Queue::setDriver($fake);
        return $fake;
    }

    // ── DriverInterface ───────────────────────────────────────────────────────

    public function push(Job $job): void
    {
        $queue = $job->queue ?? 'default';
        $this->jobs[] = ['queue' => $queue, 'job' => $job];
    }

    public function pop(string $queue): ?array  { return null; }
    public function ack(int $id): void          {}
    public function release(int $id, int $delay = 0): void {}
    public function fail(int $id, string $queue, string $payload, \Throwable $e): void {}
    public function retryFailed(): int          { return 0; }
    public function flushFailed(): int          { return 0; }
    public function failedCount(): int          { return 0; }

    // ── Assertions ────────────────────────────────────────────────────────────

    public function assertPushed(string $jobClass, ?callable $callback = null): void
    {
        $matching = array_filter(
            $this->jobs,
            fn($j) => $j['job'] instanceof $jobClass
                && ($callback === null || $callback($j['job']))
        );
        Assert::assertNotEmpty(
            $matching,
            "Expected job [$jobClass] to be pushed, but it was not."
        );
    }

    public function assertNotPushed(string $jobClass): void
    {
        $matching = array_filter($this->jobs, fn($j) => $j['job'] instanceof $jobClass);
        Assert::assertEmpty($matching, "Expected job [$jobClass] NOT to be pushed, but it was.");
    }

    public function assertPushedOn(string $queue, string $jobClass): void
    {
        $matching = array_filter(
            $this->jobs,
            fn($j) => $j['queue'] === $queue && $j['job'] instanceof $jobClass
        );
        Assert::assertNotEmpty(
            $matching,
            "Expected job [$jobClass] to be pushed on queue [$queue], but it was not."
        );
    }

    public function assertNothingPushed(): void
    {
        Assert::assertEmpty($this->jobs, 'Expected no jobs to be pushed, but ' . count($this->jobs) . ' were pushed.');
    }

    public function assertPushedCount(int $count): void
    {
        Assert::assertCount($count, $this->jobs, "Expected $count job(s) to be pushed.");
    }

    /** @return Job[] */
    public function pushed(string $jobClass): array
    {
        return array_values(array_map(
            fn($j) => $j['job'],
            array_filter($this->jobs, fn($j) => $j['job'] instanceof $jobClass)
        ));
    }
}
