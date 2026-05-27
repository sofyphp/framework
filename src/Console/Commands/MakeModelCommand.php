<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class MakeModelCommand extends Command
{
    protected string $signature   = 'make:model {name}';
    protected string $description = 'Create a new model class';

    public function handle(): int
    {
        $name = $this->argument('name');

        if ($name === null) {
            $this->error('Missing argument: name');
            return 1;
        }

        $name = ucfirst((string) $name);
        $dir  = base_path('Main/Models');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "$dir/$name.php";

        if (file_exists($path)) {
            $this->warn("Model [$name] already exists.");
            return 1;
        }

        $table = $this->toSnakeCase($name) . 's';
        file_put_contents($path, $this->stub($name, $table));

        $this->success("Model [$name] created successfully.");
        $this->line("  → Main/Models/$name.php");
        return 0;
    }

    private function stub(string $name, string $table): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Main\Models;

        use Sofy\Database\Model;

        class $name extends Model
        {
            protected static string \$table    = '$table';
            protected static array  \$fillable = [];
        }
        PHP;
    }

    private function toSnakeCase(string $name): string
    {
        return strtolower((string) preg_replace('/[A-Z]/', '_$0', lcfirst($name)));
    }
}
