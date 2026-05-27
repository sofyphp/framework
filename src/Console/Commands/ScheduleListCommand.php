<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Sofy\Console\Schedule\Schedule;

class ScheduleListCommand extends Command
{
    protected string $signature   = 'schedule:list';
    protected string $description = 'List all scheduled tasks';

    public function handle(): int
    {
        $schedule = new Schedule();

        $file = base_path('routes/schedule.php');
        if (!file_exists($file)) {
            $this->warn('No schedule file found at routes/schedule.php');
            return 0;
        }

        require $file;

        $events = $schedule->events();

        if (empty($events)) {
            $this->info('No scheduled tasks defined.');
            return 0;
        }

        $rows = array_map(
            fn($e) => [$e->getExpression(), $e->getSummary()],
            $events,
        );

        $this->table(['Expression', 'Task'], $rows);
        return 0;
    }
}
