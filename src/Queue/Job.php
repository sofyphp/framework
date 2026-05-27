<?php

declare(strict_types=1);

namespace Sofy\Queue;

abstract class Job
{
    /** Название очереди. */
    public string $queue = 'default';

    /** Задержка перед запуском (секунды). */
    public int $delay = 0;

    /** Максимальное число попыток. */
    public int $maxAttempts = 3;

    abstract public function handle(): void;

    /** Вызывается после исчерпания всех попыток. */
    public function failed(\Throwable $e): void {}

    /** Помещает задачу в очередь. */
    public function dispatch(): void
    {
        Queue::dispatch($this);
    }

    /** Помещает задачу в очередь с задержкой. */
    public function dispatchLater(int $seconds): void
    {
        $this->delay = $seconds;
        Queue::dispatch($this);
    }
}
