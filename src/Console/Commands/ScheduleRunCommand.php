<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Sofy\Console\Schedule\Schedule;
use Throwable;

class ScheduleRunCommand extends Command
{
    protected string $signature   = 'schedule:run';
    protected string $description = 'Run all due scheduled tasks';

    public function handle(): int
    {
        $schedule = new Schedule();

        $file = base_path('routes/schedule.php');
        if (!file_exists($file)) {
            $this->warn('No schedule file found at routes/schedule.php');
            return 0;
        }

        require $file;

        $due = $schedule->dueEvents();

        if (empty($due)) {
            $this->line('No tasks are due.');
            return 0;
        }

        $errors = 0;

        foreach ($due as $event) {
            $this->comment('Running: ' . $event->getSummary());
            try {
                $event->run();
                $this->success('Done: ' . $event->getSummary());
            } catch (Throwable $e) {
                $this->error('Failed: ' . $e->getMessage());
                $errors++;
            }
        }

        return $errors > 0 ? 1 : 0;
    }
}
