<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

/**
 * Reverse `php sofy optimize` — remove the config cache, route cache and
 * preload script so the app loads config + routes live again. Use this for
 * local development; production should stay optimized.
 */
class OptimizeClearCommand extends Command
{
    protected string $signature   = 'optimize:clear';
    protected string $description  = 'Remove config cache, route cache and the preload script';

    public function handle(): int
    {
        $base  = function_exists('base_path') ? base_path() : (string) getcwd();
        $files = [
            'config cache'   => $base . '/bootstrap/cache/config.php',
            'route cache'    => $base . '/bootstrap/cache/routes.php',
            'preload script' => $base . '/bootstrap/cache/preload.php',
        ];

        $removed = 0;
        $failed  = [];
        foreach ($files as $label => $file) {
            if (!is_file($file)) {
                continue;
            }
            // @-suppressed: with APP_DEBUG on, a raw unlink() warning becomes an
            // ErrorException and aborts the whole command. Report instead.
            if (@unlink($file)) {
                $this->line("  removed {$label}");
                $removed++;
            } else {
                $failed[] = $file;
                $this->warn("  could not remove {$label} — {$file}");
            }
        }

        if ($failed !== []) {
            $this->error('Some cache files could not be removed (permission denied).');
            $this->line('They are likely owned by root from `full-install`. Fix with:');
            $this->line('  sudo rm -f ' . implode(' ', $failed));
            $this->line('  sudo chown -R www-data:www-data ' . dirname($files['config cache']));
            return 1;
        }

        if ($removed === 0) {
            $this->line('Nothing to clear — no optimization caches present.');
            return 0;
        }

        $this->success("Cleared {$removed} cache file(s). App now loads config + routes live.");
        $this->comment('Note: if php.ini still has opcache.preload pointing at the (now-deleted) script,');
        $this->comment('remove that line and reload PHP-FPM, or the next FPM start will warn.');
        return 0;
    }
}
