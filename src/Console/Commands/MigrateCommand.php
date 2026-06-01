<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Sofy\Core\Application;
use Sofy\Database\Connection;
use Throwable;

class MigrateCommand extends Command
{
    protected string $signature   = 'migrate {--rollback : Roll back the last batch of migrations} {--fresh : Drop all tables and re-run all migrations}';
    protected string $description = 'Run pending database migrations';

    private Connection $db;

    public function handle(): int
    {
        try {
            $this->db = Connection::getDefault();
        } catch (Throwable $e) {
            $this->error('Database connection failed: ' . $e->getMessage());
            return 1;
        }

        if ($this->option('fresh')) {
            return $this->fresh();
        }

        if ($this->option('rollback')) {
            return $this->rollback();
        }

        return $this->migrate();
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    private function migrate(): int
    {
        $this->ensureMigrationsTable();

        $ran     = $this->getRanMigrations();
        $files   = $this->getMigrationFiles(); // [key => absolute_path]
        $pending = array_filter($files, static fn(string $key) => !in_array($key, $ran, true), ARRAY_FILTER_USE_KEY);

        if (empty($pending)) {
            $this->info('Nothing to migrate.');
            return 0;
        }

        $batch = $this->getNextBatch();

        foreach ($pending as $key => $path) {
            $migration = require $path;
            $migration->up();
            $this->db->execute(
                'INSERT INTO migrations (migration, batch) VALUES (?, ?)',
                [$key, $batch]
            );
            $this->success("Migrated: $key");
        }

        return 0;
    }

    private function rollback(): int
    {
        $this->ensureMigrationsTable();

        $lastBatch = $this->db->query(
            'SELECT migration FROM migrations WHERE batch = (SELECT MAX(batch) FROM migrations) ORDER BY id DESC'
        );

        if (empty($lastBatch)) {
            $this->info('Nothing to roll back.');
            return 0;
        }

        foreach ($lastBatch as $row) {
            $key  = $row['migration'];
            $path = $this->resolveMigrationPath($key);
            $migration = require $path;
            $migration->down();
            $this->db->execute('DELETE FROM migrations WHERE migration = ?', [$key]);
            $this->warn("Rolled back: $key");
        }

        return 0;
    }

    private function fresh(): int
    {
        if (!$this->confirm('This will drop all tables. Continue?')) {
            $this->line('Cancelled.');
            return 0;
        }

        $tables = $this->db->query('SHOW TABLES');
        $this->db->execute('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $row) {
            $table = array_values($row)[0];
            $this->db->execute("DROP TABLE IF EXISTS `$table`");
        }
        $this->db->execute('SET FOREIGN_KEY_CHECKS = 1');

        $this->warn('All tables dropped.');
        return $this->migrate();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function ensureMigrationsTable(): void
    {
        $driver = $this->db->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $idCol  = match ($driver) {
            'pgsql'  => 'id SERIAL PRIMARY KEY',
            'sqlite' => 'id INTEGER PRIMARY KEY AUTOINCREMENT',
            default  => 'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        };
        $this->db->execute("
            CREATE TABLE IF NOT EXISTS migrations (
                $idCol,
                migration VARCHAR(255) NOT NULL,
                batch     INT NOT NULL
            )
        ");
    }

    private function getRanMigrations(): array
    {
        $rows = $this->db->query('SELECT migration FROM migrations ORDER BY id');
        return array_column($rows, 'migration');
    }

    /**
     * Returns [key => absolute_path] for all migrations (app + modules).
     *
     * App migration key:    '0001_create_users.php'
     * Module migration key: 'blog::0001_create_posts.php'
     *
     * Sorted by key so app and module migrations are interleaved chronologically.
     */
    private function getMigrationFiles(): array
    {
        $result = [];

        // App migrations
        $dir = base_path('database/migrations');
        foreach (glob("$dir/*.php") ?: [] as $path) {
            $result[basename($path)] = $path;
        }

        // Module migrations
        foreach (Application::getInstance()->getModuleLoader()->getModules() as $module) {
            $dir = $module->path('Migrations');
            foreach (glob("$dir/*.php") ?: [] as $path) {
                $key          = $module->name() . '::' . basename($path);
                $result[$key] = $path;
            }
        }

        ksort($result);
        return $result;
    }

    /**
     * Resolve the absolute file path from a migration key.
     *
     *   '0001_create_users.php'       → database/migrations/0001_create_users.php
     *   'blog::0001_create_posts.php' → modules/Blog/Migrations/0001_create_posts.php
     */
    private function resolveMigrationPath(string $key): string
    {
        if (!str_contains($key, '::')) {
            return base_path("database/migrations/$key");
        }

        [$moduleName, $filename] = explode('::', $key, 2);

        foreach (Application::getInstance()->getModuleLoader()->getModules() as $module) {
            if ($module->name() === $moduleName) {
                return $module->path("Migrations/$filename");
            }
        }

        // Fallback — let require() produce a meaningful error
        return base_path("modules/$moduleName/Migrations/$filename");
    }

    private function getNextBatch(): int
    {
        $rows = $this->db->query('SELECT MAX(batch) as batch FROM migrations');
        return (int) ($rows[0]['batch'] ?? 0) + 1;
    }
}
