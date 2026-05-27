<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class UpCommand extends Command
{
    protected string $signature   = 'up';
    protected string $description = 'Bring the application out of maintenance mode';

    public function handle(): int
    {
        $file = $this->maintenanceFile();

        if (!file_exists($file)) {
            $this->line('Application is already live.');
            return 0;
        }

        unlink($file);
        $this->success('Application is now live.');
        return 0;
    }

    private function maintenanceFile(): string
    {
        return function_exists('base_path') ? base_path('.maintenance') : '.maintenance';
    }
}
