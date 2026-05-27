<?php

declare(strict_types=1);

namespace Sofy\Database;

/**
 * Base database seeder.
 *
 * Usage:
 *   class DatabaseSeeder extends Seeder
 *   {
 *       public function run(): void
 *       {
 *           $this->call(UserSeeder::class);
 *           $this->call(PostSeeder::class);
 *       }
 *   }
 *
 *   class UserSeeder extends Seeder
 *   {
 *       public function run(): void
 *       {
 *           DB::table('users')->insertMany([...]);
 *       }
 *   }
 */
abstract class Seeder
{
    abstract public function run(): void;

    /**
     * Run another seeder from within this one.
     *
     * @param class-string<Seeder> $class
     */
    protected function call(string $class): void
    {
        $seeder = new $class();
        $seeder->run();
    }

    /**
     * Run an array of seeders.
     *
     * @param class-string<Seeder>[] $classes
     */
    protected function callAll(array $classes): void
    {
        foreach ($classes as $class) {
            $this->call($class);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function table(string $table): QueryBuilder
    {
        return Connection::getDefault()->table($table);
    }

    protected function truncate(string $table): void
    {
        Connection::getDefault()->execute("TRUNCATE TABLE `$table`");
    }

    protected function disableForeignKeys(): void
    {
        Connection::getDefault()->execute('SET FOREIGN_KEY_CHECKS=0');
    }

    protected function enableForeignKeys(): void
    {
        Connection::getDefault()->execute('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function line(string $message): void
    {
        if (PHP_SAPI === 'cli') {
            echo $message . PHP_EOL;
        }
    }
}
