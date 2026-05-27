<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Cache\Cache;
use Sofy\Console\Command;

class CacheClearCommand extends Command
{
    protected string $signature   = 'cache:clear';
    protected string $description = 'Flush the application cache';

    public function handle(): int
    {
        Cache::flush();
        $this->success('Application cache cleared.');
        return 0;
    }
}
