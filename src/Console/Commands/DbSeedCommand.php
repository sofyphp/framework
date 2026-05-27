<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Throwable;

class DbSeedCommand extends Command
{
    protected string $signature   = 'db:seed {--class=DatabaseSeeder}';
    protected string $description = 'Run the database seeders';

    public function handle(): int
    {
        $class = $this->option('class') ?? 'DatabaseSeeder';

        // Try Main\Seeders\ namespace first, then bare class name
        $fqcn = str_contains((string) $class, '\\')
            ? (string) $class
            : 'Main\\Seeders\\' . $class;

        if (!class_exists($fqcn)) {
            $this->error("Seeder class [$fqcn] not found.");
            return 1;
        }

        try {
            $this->line("Seeding: <comment>$fqcn</comment>");
            $seeder = new $fqcn();
            $seeder->run();
            $this->success('Database seeded successfully.');
        } catch (Throwable $e) {
            $this->error('Seeder failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
