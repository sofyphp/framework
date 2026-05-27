<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class MakeMigrationCommand extends Command
{
    protected string $signature   = 'make:migration {name}';
    protected string $description = 'Create a new migration file';

    public function handle(): int
    {
        $name = $this->argument('name');

        if ($name === null) {
            $this->error('Missing argument: name');
            return 1;
        }

        $name      = (string) $name;
        $timestamp = date('Y_m_d_His');
        $filename  = $timestamp . '_' . $this->toSnakeCase($name) . '.php';
        $path      = base_path("database/migrations/$filename");

        $table = $this->guessTable($name);
        file_put_contents($path, $this->stub($table));

        $this->success("Migration [$filename] created successfully.");
        $this->line("  → database/migrations/$filename");
        return 0;
    }

    private function stub(string $table): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        use Sofy\Database\Schema\Blueprint;
        use Sofy\Database\Schema\Schema;

        return new class {
            public function up(): void
            {
                Schema::create('$table', function (Blueprint \$table) {
                    \$table->id();
                    \$table->timestamps();
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('$table');
            }
        };
        PHP;
    }

    /** Пытается извлечь имя таблицы из имени миграции (create_users_table → users). */
    private function guessTable(string $name): string
    {
        if (preg_match('/(?:create|update|alter)_(.+?)(?:_table)?$/i', $name, $m)) {
            return $m[1];
        }
        return $this->toSnakeCase($name);
    }

    private function toSnakeCase(string $name): string
    {
        return strtolower(trim((string) preg_replace('/[\s\-]+/', '_', $name)));
    }
}
