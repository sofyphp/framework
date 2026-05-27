<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class DownCommand extends Command
{
    protected string $signature   = 'down {--message=We\'ll be back shortly. : Message shown to visitors} {--retry=60 : Retry-After header value in seconds} {--allow= : Comma-separated IPs allowed through}';
    protected string $description = 'Put the application into maintenance mode';

    public function handle(): int
    {
        $file = $this->maintenanceFile();

        $data = [
            'message' => $this->option('message') ?? 'We\'ll be back shortly.',
            'retry'   => (int) ($this->option('retry') ?? 60),
            'time'    => time(),
        ];

        $allow = $this->option('allow');
        if ($allow) {
            $data['allow'] = array_map('trim', explode(',', (string) $allow));
        }

        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->success('Application is now in maintenance mode.');
        return 0;
    }

    private function maintenanceFile(): string
    {
        return function_exists('base_path') ? base_path('.maintenance') : '.maintenance';
    }
}
