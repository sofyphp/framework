<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class MakePolicyCommand extends Command
{
    protected string $signature   = 'make:policy {name : Policy class name} {--model= : Model class this policy covers}';
    protected string $description = 'Create a new authorization policy class';

    public function handle(): int
    {
        $name  = (string) $this->argument('name');
        $model = (string) ($this->option('model') ?? '');

        $dir  = function_exists('base_path') ? base_path('app/Policies') : 'app/Policies';
        $file = $dir . '/' . $name . '.php';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($file)) {
            $this->error("Policy [$name] already exists.");
            return 1;
        }

        $modelClass    = $model ?: 'Model';
        $modelVar      = '$' . lcfirst($modelClass);
        $modelImport   = $model ? "\nuse App\\Models\\$model;" : '';
        $modelParam    = $model ? "$model $modelVar" : 'mixed $resource';

        $stub = <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Policies;

        use Sofy\Auth\Policy;$modelImport

        class $name extends Policy
        {
            public function view(mixed \$user, $modelParam): bool
            {
                return true;
            }

            public function create(mixed \$user): bool
            {
                return true;
            }

            public function update(mixed \$user, $modelParam): bool
            {
                return \$user->id === {$modelVar}->user_id;
            }

            public function delete(mixed \$user, $modelParam): bool
            {
                return \$user->id === {$modelVar}->user_id;
            }
        }
        PHP;

        $stub = preg_replace('/^        /m', '', $stub);
        file_put_contents($file, $stub);

        $this->success("Policy [$name] created at app/Policies/$name.php");
        $this->line("Register: Gate::policy($modelClass::class, $name::class);");

        return 0;
    }
}
