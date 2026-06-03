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
        foreach ($files as $label => $file) {
            if (is_file($file)) {
                unlink($file);
                $this->line("  removed {$label}");
                $removed++;
            }
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
