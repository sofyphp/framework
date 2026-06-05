<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

/**
 * Show the state of Sofy's background services (and Redis).
 *   php sofy service:status
 */
class ServiceStatusCommand extends Command
{
    protected string $signature   = 'service:status';
    protected string $description  = 'Show the status of Sofy background services';

    public function handle(): int
    {
        if (PHP_OS_FAMILY !== 'Linux' || trim((string) shell_exec('command -v systemctl 2>/dev/null')) === '') {
            $this->error('systemd not available on this host.');
            return 1;
        }

        $units = ['sofy-ws.service', 'sofy-queue.service', 'sofy-queue@.service',
                  'sofy-scheduler.timer', 'redis-server.service', 'redis.service'];

        foreach ($units as $u) {
            $state  = trim((string) shell_exec('systemctl is-active ' . escapeshellarg($u) . ' 2>/dev/null'));
            $enabled = trim((string) shell_exec('systemctl is-enabled ' . escapeshellarg($u) . ' 2>/dev/null'));
            if ($state === '' && ($enabled === '' || $enabled === 'not-found')) {
                continue; // unit not installed
            }
            $mark = $state === 'active' ? "\033[32m●\033[0m" : "\033[31m●\033[0m";
            $this->line(sprintf('  %s  %-26s %s (%s)', $mark, $u, $state ?: 'inactive', $enabled ?: '—'));
        }

        $this->line('');
        $this->comment('Logs: storage/logs/sofy-*.log · manage with systemctl start|stop|restart sofy-<name>');
        return 0;
    }
}
