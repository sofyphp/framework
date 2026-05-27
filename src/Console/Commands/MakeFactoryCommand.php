<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class MakeFactoryCommand extends Command
{
    protected string $signature   = 'make:factory {name : Factory class name (e.g. User)}';
    protected string $description = 'Create a new model factory';

    public function handle(): int
    {
        $name  = ucfirst((string) $this->argument('name'));
        $dir   = base_path('Main/Factories');
        $file  = "$dir/{$name}Factory.php";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($file)) {
            $this->error("Factory [{$name}Factory] already exists.");
            return 1;
        }

        $stub = <<<PHP
        <?php

        declare(strict_types=1);

        namespace Main\\Factories;

        use Sofy\\Database\\Factory;
        use Main\\Models\\$name;

        class {$name}Factory extends Factory
        {
            protected string \$model = $name::class;

            public function definition(): array
            {
                return [
                    // 'name' => 'Alice',
                ];
            }
        }
        PHP;

        $stub = preg_replace('/^        /m', '', $stub);
        file_put_contents($file, $stub);

        $this->success("Factory [{$name}Factory] created at Main/Factories/{$name}Factory.php");
        return 0;
    }
}
