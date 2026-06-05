<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

/**
 * Install Sofy's background workers as systemd services so they start on boot
 * and auto-restart on crash — the WebSocket server, the queue worker, the
 * scheduler, and the Redis package.
 *
 *   php sofy service:install all          # redis + ws + queue + scheduler
 *   php sofy service:install ws           # just the WebSocket server
 *   php sofy service:install queue --procs=2
 *   php sofy service:install redis
 *
 * Linux + systemd + root. Units are named sofy-<service>.service.
 */
class ServiceInstallCommand extends Command
{
    protected string $signature   = 'service:install {service=all : redis|ws|queue|scheduler|all} {--user=www-data : Service user} {--procs=1 : Queue worker processes}';
    protected string $description  = 'Install background workers (ws, queue, scheduler, redis) as systemd services';

    private const array SERVICES = ['redis', 'ws', 'queue', 'scheduler'];

    public function handle(): int
    {
        if (!$this->preflight()) {
            return 1;
        }

        $service = strtolower((string) $this->argument('service'));
        $targets = $service === 'all' ? self::SERVICES : [$service];

        if (array_diff($targets, self::SERVICES)) {
            $this->error("Unknown service '{$service}'. Use: " . implode(', ', self::SERVICES) . ', or all.');
            return 1;
        }

        $ok = true;
        foreach ($targets as $t) {
            $ok = match ($t) {
                'redis'     => $this->installRedis(),
                'ws'        => $this->installUnit('ws', 'Sofy WebSocket server', $this->phpExec() . ' ws:serve'),
                'queue'     => $this->installUnit('queue', 'Sofy queue worker', $this->phpExec() . ' queue:work --sleep=3'),
                'scheduler' => $this->installScheduler(),
                default     => true,
            } && $ok;
        }

        $this->line('');
        $this->success('Done. Check status with: php sofy service:status');
        return $ok ? 0 : 1;
    }

    // ── systemd units ─────────────────────────────────────────────────────────

    private function installUnit(string $name, string $description, string $execStart, array $extra = []): bool
    {
        $unit  = "sofy-$name";
        $file  = "/etc/systemd/system/$unit.service";
        $user  = (string) $this->option('user');
        $cwd   = $this->basePath();
        $procs = max(1, (int) $this->option('procs'));

        // The queue service supports N processes via a templated unit name.
        $multi = $name === 'queue' && $procs > 1;

        $extraLines = implode("\n", $extra);

        $conf = <<<UNIT
        [Unit]
        Description=$description
        After=network.target

        [Service]
        Type=simple
        User=$user
        WorkingDirectory=$cwd
        ExecStart=$execStart
        Restart=always
        RestartSec=3
        StandardOutput=append:$cwd/storage/logs/$unit.log
        StandardError=append:$cwd/storage/logs/$unit.log
        $extraLines

        [Install]
        WantedBy=multi-user.target
        UNIT;

        if (file_put_contents($file, $conf) === false) {
            $this->error("  Could not write $file (need root?)");
            return false;
        }
        $this->info("  unit → $file");

        $instances = $multi
            ? implode(' ', array_map(static fn(int $i): string => "$unit@$i.service", range(1, $procs)))
            : "$unit.service";
        // For multi-proc, the unit must be a template (sofy-queue@.service);
        // rename and enable instances.
        if ($multi) {
            @rename($file, "/etc/systemd/system/$unit@.service");
            $instances = implode(' ', array_map(static fn(int $i): string => "$unit@$i.service", range(1, $procs)));
        }

        $this->exec('systemctl daemon-reload');
        $this->exec("systemctl enable --now $instances 2>&1");
        return true;
    }

    private function installScheduler(): bool
    {
        // The scheduler runs every minute. systemd timer instead of cron.
        $cwd  = $this->basePath();
        $user = (string) $this->option('user');
        $svc  = '/etc/systemd/system/sofy-scheduler.service';
        $tmr  = '/etc/systemd/system/sofy-scheduler.timer';

        $php = $this->phpExec();
        file_put_contents($svc, <<<UNIT
        [Unit]
        Description=Sofy scheduler tick

        [Service]
        Type=oneshot
        User=$user
        WorkingDirectory=$cwd
        ExecStart=$php schedule:run
        UNIT);

        file_put_contents($tmr, <<<UNIT
        [Unit]
        Description=Run the Sofy scheduler every minute

        [Timer]
        OnCalendar=*-*-* *:*:00
        AccuracySec=1s
        Persistent=true

        [Install]
        WantedBy=timers.target
        UNIT);

        $this->info('  unit → ' . $svc);
        $this->info('  timer → ' . $tmr);
        $this->exec('systemctl daemon-reload');
        $this->exec('systemctl enable --now sofy-scheduler.timer 2>&1');
        return true;
    }

    private function installRedis(): bool
    {
        $this->line('  installing Redis…');
        if ($this->has('apt-get')) {
            $this->exec('DEBIAN_FRONTEND=noninteractive apt-get install -y redis-server 2>&1 | tail -3');
            $this->exec('systemctl enable --now redis-server 2>&1 || systemctl enable --now redis 2>&1');
        } elseif ($this->has('dnf')) {
            $this->exec('dnf install -y redis 2>&1 | tail -3');
            $this->exec('systemctl enable --now redis 2>&1');
        } elseif ($this->has('yum')) {
            $this->exec('yum install -y redis 2>&1 | tail -3');
            $this->exec('systemctl enable --now redis 2>&1');
        } else {
            $this->warn('  no apt/dnf/yum found — install redis-server manually.');
            return false;
        }
        $this->comment('  Set CACHE_DRIVER=redis / SESSION_DRIVER=redis / BROADCAST_DRIVER=redis in .env to use it.');
        return true;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function preflight(): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->error('service:install supports Linux + systemd only.');
            return false;
        }
        if (function_exists('posix_getuid') && posix_getuid() !== 0) {
            $this->error('Run as root: sudo php sofy service:install ' . $this->argument('service'));
            return false;
        }
        if (!$this->has('systemctl')) {
            $this->error('systemd (systemctl) not found.');
            return false;
        }
        return true;
    }

    private function phpExec(): string
    {
        $php = trim((string) shell_exec('command -v php 2>/dev/null')) ?: '/usr/bin/php';
        return $php . ' ' . $this->basePath() . '/sofy';
    }

    private function basePath(): string
    {
        return function_exists('base_path') ? base_path() : (string) getcwd();
    }

    private function has(string $bin): bool
    {
        return trim((string) shell_exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null')) !== '';
    }

    private function exec(string $cmd): void
    {
        $this->comment("  \$ $cmd");
        passthru($cmd);
    }
}
