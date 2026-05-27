<?php

declare(strict_types=1);

namespace Sofy\Queue;

use Sofy\Queue\Drivers\DriverInterface;
use Throwable;

class Worker
{
    private bool $shouldStop = false;

    public function __construct(private readonly DriverInterface $driver) {}

    /**
     * Запускает воркер в бесконечном цикле.
     *
     * @param string $queue   Название очереди
     * @param int    $sleep   Пауза (сек) когда очередь пуста
     */
    public function run(string $queue, int $sleep = 3): void
    {
        // Обрабатываем SIGTERM/SIGINT для graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function (): void { $this->shouldStop = true; });
            pcntl_signal(SIGINT,  function (): void { $this->shouldStop = true; });
        }

        while (!$this->shouldStop) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            $item = $this->driver->pop($queue);

            if ($item === null) {
                sleep($sleep);
                continue;
            }

            $this->process($item);
        }
    }

    /** Обрабатывает одну задачу. Возвращает true при успехе. */
    public function process(array $item): bool
    {
        ['id' => $id, 'job' => $job, 'attempts' => $attempts,
         'max_attempts' => $max, 'payload' => $payload, 'queue' => $queue] = $item;

        try {
            $job->handle();
            $this->driver->ack($id);
            return true;
        } catch (Throwable $e) {
            if ($attempts >= $max) {
                $this->driver->fail($id, $queue, $payload, $e);
                try { $job->failed($e); } catch (Throwable) {}
            } else {
                // Экспоненциальная задержка: 5s, 10s, 20s…
                $this->driver->release($id, 5 * (2 ** ($attempts - 1)));
            }
            return false;
        }
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }
}
