<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Sofy\Core\Application;

class MakeModuleCommand extends Command
{
    protected string $signature   = 'make:module {name : Module name in PascalCase, e.g. Blog}';
    protected string $description = 'Scaffold a new module in the modules/ directory';

    public function handle(): int
    {
        $name = trim($this->argument('name') ?? '');

        if ($name === '') {
            $this->error('Module name is required.');
            return 1;
        }

        // Normalise to PascalCase
        $name = str_replace(['-', '_', ' '], '', ucwords($name, '-_ '));

        $basePath = Application::getInstance()->basePath("modules/$name");

        if (is_dir($basePath)) {
            $this->error("Module [$name] already exists at modules/$name/");
            return 1;
        }

        mkdir($basePath, 0755, recursive: true);

        $this->writeMainClass($basePath, $name);
        $this->writeConfig($basePath, $name);
        $this->writeRoutes($basePath, $name);

        $this->success("Module [$name] created.");
        $this->line('');
        $this->line("  modules/$name/$name.php  ← main class (extends Module)");
        $this->line("  modules/$name/config.php ← configuration");
        $this->line("  modules/$name/routes.php ← HTTP routes");
        $this->line('');
        $this->info('Updating autoloader...');
        $this->registerComposerNamespace($name, Application::getInstance());
        $this->composerDumpAutoload();

        return 0;
    }

    // ── Stubs ─────────────────────────────────────────────────────────────────

    private function writeMainClass(string $basePath, string $name): void
    {
        $slug = strtolower($name);

        $stub = <<<PHP
        <?php

        declare(strict_types=1);

        namespace $name;

        use Sofy\\Core\\Application;
        use Sofy\\Core\\Module;

        class $name extends Module
        {
            public function name(): string
            {
                return '$slug';
            }

            /**
             * Module configuration — accessible via config('$slug.*').
             */
            public function config(): array
            {
                return require \$this->path('config.php');
            }

            /**
             * Register services/bindings into the DI container.
             */
            public function register(Application \$app): void
            {
                // \$app->singleton(SomeService::class, fn() => new SomeService());
            }

            /**
             * Subscribe to events, register observers, model observers, etc.
             * Called after all modules are registered.
             */
            public function boot(Application \$app): void
            {
                //
            }

            /**
             * Console commands provided by this module.
             *
             * @return array<class-string>
             */
            public function commands(): array
            {
                return [
                    // Commands\\{$name}Command::class,
                ];
            }

            // Routes are defined in routes.php — no need to override routes() here.
        }
        PHP;

        file_put_contents("$basePath/$name.php", $stub . "\n");
    }

    private function writeConfig(string $basePath, string $name): void
    {
        $slug = strtolower($name);

        $stub = <<<PHP
        <?php

        /**
         * $name module configuration.
         * Accessible via config('$slug.*').
         */
        return [
            // 'key' => 'value',
        ];
        PHP;

        file_put_contents("$basePath/config.php", $stub . "\n");
    }

    private function writeRoutes(string $basePath, string $name): void
    {
        $slug = strtolower($name);

        $stub = <<<PHP
        <?php

        declare(strict_types=1);

        use Sofy\\Http\\Router;

        /** @var Router \$router */

        // ── Web routes ────────────────────────────────────────────────────────────────

        \$router->web(function (Router \$router): void {
            // \$router->get('/$slug',        [Controllers\\{$name}Controller::class, 'index']);
            // \$router->get('/$slug/{id}',   [Controllers\\{$name}Controller::class, 'show']);
            // \$router->resource('$slug',     Controllers\\{$name}Controller::class);
        });

        // ── API routes (auto-prefixed with /api) ──────────────────────────────────────

        \$router->api(function (Router \$router): void {
            // \$router->get('/$slug',         [Controllers\\Api\\{$name}Controller::class, 'index']);
            // \$router->apiResource('$slug',   Controllers\\Api\\{$name}Controller::class);
        });
        PHP;

        file_put_contents("$basePath/routes.php", $stub . "\n");
    }

    private function registerComposerNamespace(string $name, Application $app): void
    {
        $composerFile = $app->basePath('composer.json');
        $composer     = json_decode((string) file_get_contents($composerFile), true);

        $key = $name . '\\';
        $val = "modules/$name/";

        if (($composer['autoload']['psr-4'][$key] ?? null) === $val) {
            return; // already registered
        }

        $composer['autoload']['psr-4'][$key] = $val;

        file_put_contents(
            $composerFile,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }

    private function composerDumpAutoload(): void
    {
        $composer = $this->findComposer();
        passthru("$composer dump-autoload --quiet", $code);

        if ($code === 0) {
            $this->success('Autoloader updated.');
        } else {
            $this->warn('Could not update autoloader automatically. Run: composer dump-autoload');
        }
    }

    private function findComposer(): string
    {
        $local = Application::getInstance()->basePath('vendor/bin/composer');
        if (file_exists($local)) {
            return '"' . PHP_BINARY . '" ' . escapeshellarg($local);
        }
        return 'composer';
    }
}
