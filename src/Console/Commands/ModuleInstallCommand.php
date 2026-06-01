<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Sofy\Core\Application;
use Sofy\Core\Module;
use Sofy\Database\Connection;
use Throwable;

/**
 * module:install {name}
 *
 * Run this command after dropping a ready-made module into modules/:
 *   1. Validates the module directory and class exist
 *   2. Runs composer dump-autoload (registers the namespace)
 *   3. Loads the module and calls Module::install()
 *   4. Runs any pending migrations from modules/{Name}/Migrations/
 */
class ModuleInstallCommand extends Command
{
    protected string $signature   = 'module:install {name : Module directory name, e.g. Blog}';
    protected string $description = 'Install a module: autoload, install hook, migrations';

    public function handle(): int
    {
        $name = trim($this->argument('name') ?? '');

        if ($name === '') {
            $this->error('Module name is required.');
            return 1;
        }

        $app      = Application::getInstance();
        $moduleDir = $app->basePath("modules/$name");

        if (!is_dir($moduleDir)) {
            $this->error("Module directory not found: modules/$name/");
            $this->line("Place the module folder there, then re-run this command.");
            return 1;
        }

        $classFile = "$moduleDir/$name.php";
        if (!file_exists($classFile)) {
            $this->error("Main class file not found: modules/$name/$name.php");
            return 1;
        }

        // ── Step 1: composer dump-autoload ────────────────────────────────────
        $this->info("[1/3] Updating autoloader...");
        $this->registerComposerNamespace($name, $app);
        $composer = $this->findComposer($app);
        passthru("$composer dump-autoload --quiet", $exitCode);

        if ($exitCode !== 0) {
            $this->warn('composer dump-autoload failed. Trying to continue anyway...');
        } else {
            $this->success('Autoloader updated.');
        }

        // ── Step 2: load module & call install() ──────────────────────────────
        $this->info("[2/3] Running module install hook...");

        $class = "$name\\$name";

        if (!class_exists($class)) {
            require_once $classFile;
        }

        if (!class_exists($class)) {
            $this->error("Class $class not found after autoload. Check the namespace in $name.php.");
            return 1;
        }

        $module = new $class();

        if (!$module instanceof Module) {
            $this->error("$class must extend Sofy\\Core\\Module.");
            return 1;
        }

        // Merge config so install() can access config('{slug}.*')
        $config = $module->config();
        if ($config !== []) {
            $app->mergeConfig($module->name(), $config);
        }

        try {
            $module->install($app);
            $this->success("Install hook completed.");
        } catch (Throwable $e) {
            $this->warn("Install hook threw: " . $e->getMessage());
        }

        // ── Step 3: run module migrations ─────────────────────────────────────
        $this->info("[3/3] Running migrations...");

        $migrationsDir = $module->path('Migrations');
        $files = glob("$migrationsDir/*.php") ?: [];

        if (empty($files)) {
            $this->line('    No migrations found — skipping.');
        } else {
            try {
                $db = Connection::getDefault();
                $this->ensureMigrationsTable($db);
                $ran   = array_column($db->query('SELECT migration FROM migrations ORDER BY id'), 'migration');
                $batch = (int) (($db->query('SELECT MAX(batch) as b FROM migrations')[0]['b'] ?? 0)) + 1;

                $count = 0;
                sort($files);
                foreach ($files as $path) {
                    $key = $module->name() . '::' . basename($path);
                    if (in_array($key, $ran, true)) {
                        continue;
                    }
                    $migration = require $path;
                    $migration->up();
                    $db->execute('INSERT INTO migrations (migration, batch) VALUES (?, ?)', [$key, $batch]);
                    $this->success("    Migrated: " . basename($path));
                    $count++;
                }

                if ($count === 0) {
                    $this->line('    All migrations already ran — nothing to do.');
                }
            } catch (Throwable $e) {
                $this->warn('Could not run migrations: ' . $e->getMessage());
            }
        }

        $this->line('');
        $this->success("Module [$name] installed successfully.");
        return 0;
    }

    private function ensureMigrationsTable(Connection $db): void
    {
        $idCol = match ($db->getDriverName()) {
            'pgsql'  => 'id SERIAL PRIMARY KEY',
            'sqlite' => 'id INTEGER PRIMARY KEY AUTOINCREMENT',
            default  => 'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        };
        $db->execute("
            CREATE TABLE IF NOT EXISTS migrations (
                $idCol,
                migration VARCHAR(255) NOT NULL,
                batch     INT NOT NULL
            )
        ");
    }

    private function registerComposerNamespace(string $name, Application $app): void
    {
        $composerFile = $app->basePath('composer.json');
        $composer     = json_decode((string) file_get_contents($composerFile), true);

        $key = $name . '\\';
        $val = "modules/$name/";

        if (($composer['autoload']['psr-4'][$key] ?? null) === $val) {
            return;
        }

        $composer['autoload']['psr-4'][$key] = $val;

        file_put_contents(
            $composerFile,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }

    private function findComposer(Application $app): string
    {
        $local = $app->basePath('vendor/bin/composer');
        if (file_exists($local)) {
            return '"' . PHP_BINARY . '" ' . escapeshellarg($local);
        }
        return 'composer';
    }
}
