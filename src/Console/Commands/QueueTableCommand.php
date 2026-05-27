<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class QueueTableCommand extends Command
{
    protected string $signature   = 'queue:table';
    protected string $description = 'Create migrations for the jobs and failed_jobs tables';

    public function handle(): int
    {
        $timestamp = date('Y_m_d_His');
        $filename  = $timestamp . '_create_queue_tables.php';
        $path      = base_path("database/migrations/$filename");

        file_put_contents($path, $this->stub());

        $this->success("Migration [$filename] created successfully.");
        $this->line("  → database/migrations/$filename");
        $this->line();
        $this->comment("Run 'php sofy migrate' to apply it.");
        return 0;
    }

    private function stub(): string
    {
        return <<<'PHP'
        <?php

        declare(strict_types=1);

        use Sofy\Database\Schema\Blueprint;
        use Sofy\Database\Schema\Schema;

        return new class {
            public function up(): void
            {
                Schema::create('jobs', function (Blueprint $table) {
                    $table->id();
                    $table->string('queue', 100)->default('default');
                    $table->longText('payload');
                    $table->unsignedTinyInteger('attempts')->default(0);
                    $table->unsignedTinyInteger('max_attempts')->default(3);
                    $table->unsignedInteger('available_at');
                    $table->unsignedInteger('reserved_at')->nullable();
                    $table->unsignedInteger('failed_at')->nullable();
                    $table->unsignedInteger('created_at');
                });

                Schema::create('failed_jobs', function (Blueprint $table) {
                    $table->id();
                    $table->string('queue', 100);
                    $table->longText('payload');
                    $table->longText('exception');
                    $table->unsignedInteger('failed_at');
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('failed_jobs');
                Schema::dropIfExists('jobs');
            }
        };
        PHP;
    }
}
