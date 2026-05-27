<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class MakeFlagCommand extends Command
{
    protected string $signature   = 'make:flag {name}';
    protected string $description = 'Create a new feature flag definition file';

    public function handle(): int
    {
        $name = $this->argument('name');

        if ($name === null) {
            $this->error('Missing argument: name');
            return 1;
        }

        $name = (string) $name;
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? $name);
        $className = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name))) . 'Flag';

        $path = base_path("Main/Flags/$className.php");

        if (file_exists($path)) {
            $this->warn("Flag [$className] already exists.");
            return 1;
        }

        if (!is_dir(base_path('Main/Flags'))) {
            mkdir(base_path('Main/Flags'), 0755, true);
        }

        file_put_contents($path, $this->stub($className, $slug));

        $this->success("Flag [$className] created successfully.");
        $this->line("  → Main/Flags/$className.php");
        $this->line("  Register in bootstrap: Flag::define('$slug', \$flag->resolve());");
        return 0;
    }

    private function stub(string $className, string $slug): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Main\Flags;

        use Sofy\Feature\Flag;

        /**
         * Feature flag: $slug
         *
         * Register in bootstrap.php:
         *   Flag::define('$slug', (new $className)->resolve());
         */
        class $className
        {
            public function resolve(): bool|\\Closure
            {
                // Return true/false for a static flag, or a closure for dynamic logic:
                //
                //   return true;
                //   return Flag::percent(20);        // 20% rollout
                //   return Flag::users([1, 2, 3]);   // specific users
                //   return Flag::env('local');        // environment-based
                //
                return false;
            }
        }
        PHP;
    }
}
