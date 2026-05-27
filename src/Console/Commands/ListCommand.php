<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class ListCommand extends Command
{
    protected string $signature   = 'list';
    protected string $description = 'List all available commands';

    private array $commands = [];

    public function setCommands(array $commands): void
    {
        $this->commands = $commands;
    }

    public function handle(): int
    {
        $this->line();
        $this->info('Sofy Framework');
        $this->line();
        $this->comment('Usage:');
        $this->line('  php sofy <command> [arguments] [options]');
        $this->line();
        $this->comment('Available commands:');

        $rows = [];
        foreach ($this->commands as $name => $class) {
            /** @var Command $instance */
            $instance = new $class();
            $rows[]   = [$name, $instance->getDescription()];
        }

        usort($rows, static fn(array $a, array $b) => strcmp($a[0], $b[0]));

        $maxLen = max(array_map(static fn(array $r) => strlen($r[0]), $rows));

        foreach ($rows as [$name, $description]) {
            $this->line("  \033[32m" . str_pad($name, $maxLen) . "\033[0m  " . $description);
        }

        $this->line();
        return 0;
    }
}
