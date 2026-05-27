<?php

declare(strict_types=1);

namespace Sofy\Queue;

use Sofy\Database\Connection;
use Sofy\Queue\Drivers\DatabaseDriver;
use Sofy\Queue\Drivers\DriverInterface;

/**
 * Статический фасад очереди задач.
 *
 * Использование:
 *   Queue::dispatch(new SendEmailJob($user));
 *   Queue::later(60, new GenerateReportJob());
 *   (new SendEmailJob($user))->dispatch();
 */
class Queue
{
    private static ?DriverInterface $driver = null;

    public static function setDriver(DriverInterface $driver): void
    {
        static::$driver = $driver;
    }

    public static function getDriver(): DriverInterface
    {
        if (static::$driver === null) {
            static::$driver = new DatabaseDriver(Connection::getDefault());
        }
        return static::$driver;
    }

    /** Помещает задачу в очередь немедленно. */
    public static function dispatch(Job $job): void
    {
        static::getDriver()->push($job);
    }

    /** Помещает задачу в очередь с задержкой (секунды). */
    public static function later(int $seconds, Job $job): void
    {
        $job->delay = $seconds;
        static::getDriver()->push($job);
    }
}
