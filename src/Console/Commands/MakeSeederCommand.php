<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class MakeSeederCommand extends Command
{
    protected string $signature   = 'make:seeder {name}';
    protected string $description = 'Create a new seeder class';

    public function handle(): int
    {
        $name = $this->argument('name');

        if ($name === null) {
            $this->error('Missing argument: name');
            return 1;
        }

        $name = ucfirst((string) $name);
        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        $dir = base_path('Main/Seeders');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "$dir/$name.php";

        if (file_exists($path)) {
            $this->warn("Seeder [$name] already exists.");
            return 1;
        }

        file_put_contents($path, $this->stub($name));

        $this->success("Seeder [$name] created successfully.");
        $this->line("  → Main/Seeders/$name.php");
        return 0;
    }

    private function stub(string $name): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Main\Seeders;

        use Sofy\Database\Seeder;

        class $name extends Seeder
        {
            public function run(): void
            {
                // \$this->table('users')->insert([...]);
            }
        }
        PHP;
    }
}
