<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class MakeJobCommand extends Command
{
    protected string $signature   = 'make:job {name} {--sync : Use SyncDriver (run immediately, no queue)}';
    protected string $description = 'Create a new job class';

    public function handle(): int
    {
        $name = $this->argument('name');

        if ($name === null) {
            $this->error('Missing argument: name');
            return 1;
        }

        $name = ucfirst((string) $name);
        if (!str_ends_with($name, 'Job')) {
            $name .= 'Job';
        }

        $dir = base_path('Main/Jobs');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "$dir/$name.php";

        if (file_exists($path)) {
            $this->warn("Job [$name] already exists.");
            return 1;
        }

        file_put_contents($path, $this->stub($name));

        $this->success("Job [$name] created successfully.");
        $this->line("  → Main/Jobs/$name.php");
        return 0;
    }

    private function stub(string $name): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Main\Jobs;

        use Sofy\Queue\Job;

        class $name extends Job
        {
            public string \$queue = 'default';

            public function __construct(
                // public readonly SomeModel \$model,
            ) {}

            public function handle(): void
            {
                // Логика задачи
            }

            public function failed(\Throwable \$e): void
            {
                // Вызывается после исчерпания всех попыток
            }
        }
        PHP;
    }
}
