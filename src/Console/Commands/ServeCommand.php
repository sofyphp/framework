<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class ServeCommand extends Command
{
    protected string $signature   = 'serve {--host=127.0.0.1 : Server host} {--port=8000 : Server port}';
    protected string $description = 'Start the built-in PHP development server';

    public function handle(): int
    {
        $host   = $this->option('host') ?: '127.0.0.1';
        $port   = (int) ($this->option('port') ?: 8000);
        $public = $this->publicPath();

        if (!is_dir($public)) {
            $this->error("Public directory not found: $public");
            return 1;
        }

        $addr = "$host:$port";

        $this->info("Sofy development server started.");
        $this->line("  Local:   <fg=cyan>http://$addr</>");
        $this->line("  Press Ctrl+C to stop.");
        $this->line();

        // passthru keeps the process attached so Ctrl+C propagates correctly
        passthru(
            sprintf('%s -S %s -t %s', PHP_BINARY, escapeshellarg($addr), escapeshellarg($public)),
            $exitCode,
        );

        return $exitCode ?? 0;
    }

    private function publicPath(): string
    {
        return function_exists('base_path') ? base_path('public') : (getcwd() . '/public');
    }
}
