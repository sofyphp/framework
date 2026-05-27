<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class RouteClearCommand extends Command
{
    protected string $signature   = 'route:clear';
    protected string $description = 'Remove the route cache file';

    public function handle(): int
    {
        $file = function_exists('base_path') ? base_path('bootstrap/cache/routes.php') : 'bootstrap/cache/routes.php';

        if (!file_exists($file)) {
            $this->line('Route cache not found.');
            return 0;
        }

        unlink($file);
        $this->success('Route cache cleared.');
        return 0;
    }
}
