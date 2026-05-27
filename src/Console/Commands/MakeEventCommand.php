<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class MakeEventCommand extends Command
{
    protected string $signature   = 'make:event {name}';
    protected string $description = 'Create a new event class';

    public function handle(): int
    {
        $name = $this->argument('name');

        if ($name === null) {
            $this->error('Missing argument: name');
            return 1;
        }

        $name = ucfirst((string) $name);

        $dir  = base_path('Main/Events');
        $path = "$dir/$name.php";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($path)) {
            $this->warn("Event [$name] already exists.");
            return 1;
        }

        file_put_contents($path, $this->stub($name));

        $this->success("Event [$name] created successfully.");
        $this->line("  → Main/Events/$name.php");
        return 0;
    }

    private function stub(string $name): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Main\Events;

        class $name
        {
            public function __construct(
                // public readonly mixed \$payload,
            ) {}
        }
        PHP;
    }
}
