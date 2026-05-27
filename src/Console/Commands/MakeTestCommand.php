<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class MakeTestCommand extends Command
{
    protected string $signature   = 'make:test {name : Test class name} {--unit : Create a unit test instead of a feature test}';
    protected string $description = 'Create a new test class';

    public function handle(): int
    {
        $name = ucfirst((string) $this->argument('name'));
        $unit = (bool) $this->option('unit');

        $dir  = base_path($unit ? 'tests/Unit' : 'tests/Feature');
        $file = "$dir/{$name}Test.php";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($file)) {
            $this->error("Test [{$name}Test] already exists.");
            return 1;
        }

        $ns   = $unit ? 'Tests\\Unit' : 'Tests\\Feature';
        $stub = $this->stub($name, $ns, $unit);
        file_put_contents($file, $stub);

        $subDir = $unit ? 'tests/Unit' : 'tests/Feature';
        $this->success("Test [{$name}Test] created at {$subDir}/{$name}Test.php");
        return 0;
    }

    private function stub(string $name, string $namespace, bool $unit): string
    {
        if ($unit) {
            return <<<PHP
            <?php

            declare(strict_types=1);

            namespace $namespace;

            use PHPUnit\\Framework\\TestCase;

            class {$name}Test extends TestCase
            {
                public function test_example(): void
                {
                    \$this->assertTrue(true);
                }
            }
            PHP;
        }

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace $namespace;

        use Sofy\\Testing\\TestCase;

        class {$name}Test extends TestCase
        {
            public function test_example(): void
            {
                \$response = \$this->get('/');
                \$response->assertOk();
            }
        }
        PHP;
    }
}
