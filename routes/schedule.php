<?php

declare(strict_types=1);

/**
 * Расписание задач планировщика.
 *
 * Переменная $schedule доступна автоматически.
 *
 * Примеры:
 *   $schedule->command('queue:retry')->daily();
 *   $schedule->command('reports:generate')->weeklyOn(1, '08:00')->description('Weekly report');
 *   $schedule->call(fn() => logger('ping'))->everyFiveMinutes();
 *
 * Cron-запись для запуска каждую минуту:
 *   * * * * * cd /path/to/project && php sofy schedule:run >> /dev/null 2>&1
 */
