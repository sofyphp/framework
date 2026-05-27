<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Sofy\Queue\Queue;

class QueueRetryCommand extends Command
{
    protected string $signature   = 'queue:retry';
    protected string $description = 'Retry all failed jobs';

    public function handle(): int
    {
        $driver = Queue::getDriver();
        $count  = $driver->failedCount();

        if ($count === 0) {
            $this->info('No failed jobs found.');
            return 0;
        }

        $retried = $driver->retryFailed();
        $this->success("$retried failed job(s) pushed back to queue.");
        return 0;
    }
}
