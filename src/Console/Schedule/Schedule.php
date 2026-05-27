<?php

declare(strict_types=1);

namespace Sofy\Console\Schedule;

class Schedule
{
    /** @var Event[] */
    private array $events = [];

    /**
     * Добавляет консольную команду в расписание.
     *
     * Пример:
     *   $schedule->command('newsletter:send')->daily();
     *   $schedule->command('reports:make', ['--format=pdf'])->weeklyOn(1, '08:00');
     */
    public function command(string $command, array $arguments = []): Event
    {
        $args  = $arguments ? ' ' . implode(' ', $arguments) : '';
        $event = new Event('php sofy ' . $command . $args);
        $this->events[] = $event;
        return $event;
    }

    /**
     * Добавляет произвольный callable в расписание.
     *
     * Пример:
     *   $schedule->call(fn() => Cache::flush())->hourly();
     */
    public function call(callable $callback): Event
    {
        $event = new Event($callback);
        $this->events[] = $event;
        return $event;
    }

    /** @return Event[] */
    public function dueEvents(): array
    {
        return array_values(array_filter($this->events, fn(Event $e) => $e->isDue()));
    }

    /** @return Event[] */
    public function events(): array
    {
        return $this->events;
    }
}
