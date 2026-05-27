<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Sofy\Queue\Queue;
use Sofy\Queue\Worker;

class QueueWorkCommand extends Command
{
    protected string $signature   = 'queue:work {--queue=default : Queue name to process} {--sleep=3 : Seconds to sleep when queue is empty} {--once : Process only one job and exit}';
    protected string $description = 'Start processing jobs in the queue';

    public function handle(): int
    {
        $queue  = (string) ($this->option('queue') ?? 'default');
        $sleep  = (int)    ($this->option('sleep')  ?? 3);
        $once   = (bool)   $this->option('once');

        $worker = new Worker(Queue::getDriver());

        if ($once) {
            $item = Queue::getDriver()->pop($queue);
            if ($item === null) {
                $this->line('No jobs available.');
                return 0;
            }
            $ok = $worker->process($item);
            if ($ok) {
                $this->success('Job processed successfully.');
            } else {
                $this->error('Job failed.');
            }
            return $ok ? 0 : 1;
        }

        $this->info("Worker started. Queue: [$queue]. Press Ctrl+C to stop.");
        $this->line();

        $worker->run($queue, $sleep);
        return 0;
    }
}
