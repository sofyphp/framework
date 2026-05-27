<?php

declare(strict_types=1);

namespace Sofy\Queue\Drivers;

use Sofy\Queue\Job;
use Throwable;

/**
 * Выполняет задачи синхронно в том же процессе.
 * Удобен для тестов и локальной разработки.
 */
class SyncDriver implements DriverInterface
{
    public function push(Job $job): void
    {
        try {
            $job->handle();
        } catch (Throwable $e) {
            $job->failed($e);
            throw $e;
        }
    }

    public function pop(string $queue): ?array          { return null; }
    public function ack(int $id): void                  {}
    public function release(int $id, int $delay = 0): void {}
    public function fail(int $id, string $queue, string $payload, Throwable $e): void {}
    public function retryFailed(): int                  { return 0; }
    public function flushFailed(): int                  { return 0; }
    public function failedCount(): int                  { return 0; }
}
