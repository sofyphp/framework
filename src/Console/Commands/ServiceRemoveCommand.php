<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

/**
 * Stop, disable and remove a Sofy systemd service.
 *   php sofy service:remove ws|queue|scheduler|all
 * (Redis is left installed — remove it with your package manager.)
 */
class ServiceRemoveCommand extends Command
{
    protected string $signature   = 'service:remove {service : ws|queue|scheduler|all}';
    protected string $description  = 'Remove a Sofy background service (systemd unit)';

    public function handle(): int
    {
        if (function_exists('posix_getuid') && posix_getuid() !== 0) {
            $this->error('Run as root: sudo php sofy service:remove ' . $this->argument('service'));
            return 1;
        }

        $service = strtolower((string) $this->argument('service'));
        $map = [
            'ws'        => ['sofy-ws.service'],
            'queue'     => ['sofy-queue.service', 'sofy-queue@.service'],
            'scheduler' => ['sofy-scheduler.timer', 'sofy-scheduler.service'],
        ];
        $targets = $service === 'all' ? array_merge(...array_values($map)) : ($map[$service] ?? null);

        if ($targets === null) {
            $this->error('Unknown service. Use: ws | queue | scheduler | all.');
            return 1;
        }

        foreach ($targets as $unit) {
            $glob = str_contains($unit, '@') ? 'sofy-queue@*.service' : $unit;
            passthru('systemctl disable --now ' . escapeshellarg($glob) . ' 2>/dev/null');
            @array_map('unlink', glob('/etc/systemd/system/' . $unit) ?: []);
        }
        passthru('systemctl daemon-reload');
        $this->success('Removed: ' . implode(', ', $targets));
        return 0;
    }
}
