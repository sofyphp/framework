<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class MakeObserverCommand extends Command
{
    protected string $signature   = 'make:observer {name : Observer class name} {--model= : The model class to observe}';
    protected string $description = 'Create a new model observer';

    public function handle(): int
    {
        $name  = ucfirst((string) $this->argument('name'));
        $model = $this->option('model') ?? 'Model';
        $dir   = base_path('Main/Observers');
        $file  = "$dir/$name.php";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($file)) {
            $this->error("Observer [$name] already exists.");
            return 1;
        }

        $modelUse   = "use Main\\Models\\$model;";
        $modelParam = strtolower((string) $model);

        $stub = <<<PHP
        <?php

        declare(strict_types=1);

        namespace Main\\Observers;

        $modelUse

        class $name
        {
            public function creating($model \$$modelParam): void {}

            public function created($model \$$modelParam): void {}

            public function updating($model \$$modelParam): void {}

            public function updated($model \$$modelParam): void {}

            public function deleting($model \$$modelParam): void {}

            public function deleted($model \$$modelParam): void {}
        }
        PHP;

        $stub = preg_replace('/^        /m', '', $stub);
        file_put_contents($file, $stub);

        $this->success("Observer [$name] created at Main/Observers/$name.php");
        $this->line("  Register it in a service provider or boot method:");
        $this->line("  {$model}::observe($name::class);");
        return 0;
    }
}
