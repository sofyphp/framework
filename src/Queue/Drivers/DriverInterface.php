<?php

declare(strict_types=1);

namespace Sofy\Queue\Drivers;

use Sofy\Queue\Job;
use Throwable;

interface DriverInterface
{
    /** Добавляет задачу в очередь. */
    public function push(Job $job): void;

    /**
     * Берёт следующую доступную задачу из очереди и резервирует её.
     * Возвращает null, если очередь пуста.
     *
     * @return array{id: int, job: Job, attempts: int, max_attempts: int, payload: string, queue: string}|null
     */
    public function pop(string $queue): ?array;

    /** Подтверждает успешное выполнение — удаляет задачу из очереди. */
    public function ack(int $id): void;

    /** Возвращает задачу обратно в очередь (с опциональной задержкой). */
    public function release(int $id, int $delay = 0): void;

    /** Помечает задачу как упавшую и перемещает в failed_jobs. */
    public function fail(int $id, string $queue, string $payload, Throwable $e): void;

    /** Повторяет все упавшие задачи. Возвращает количество. */
    public function retryFailed(): int;

    /** Удаляет все упавшие задачи. Возвращает количество. */
    public function flushFailed(): int;

    /** Возвращает количество упавших задач. */
    public function failedCount(): int;
}
