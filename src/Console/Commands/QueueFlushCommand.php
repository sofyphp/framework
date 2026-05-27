<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Sofy\Queue\Queue;

class QueueFlushCommand extends Command
{
    protected string $signature   = 'queue:flush';
    protected string $description = 'Delete all failed jobs';

    public function handle(): int
    {
        $count = Queue::getDriver()->flushFailed();

        if ($count === 0) {
            $this->info('No failed jobs to delete.');
            return 0;
        }

        $this->success("Deleted $count failed job(s).");
        return 0;
    }
}
