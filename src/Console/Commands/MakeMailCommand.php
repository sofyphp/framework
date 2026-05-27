<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class MakeMailCommand extends Command
{
    protected string $signature   = 'make:mail {name}';
    protected string $description = 'Create a new mailable class';

    public function handle(): int
    {
        $name = $this->argument('name');

        if ($name === null) {
            $this->error('Missing argument: name');
            return 1;
        }

        $name = ucfirst((string) $name);

        $dir  = base_path('Main/Mail');
        $path = "$dir/$name.php";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($path)) {
            $this->warn("Mailable [$name] already exists.");
            return 1;
        }

        file_put_contents($path, $this->stub($name));

        $this->success("Mailable [$name] created successfully.");
        $this->line("  → Main/Mail/$name.php");
        return 0;
    }

    private function stub(string $name): string
    {
        $viewName = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $name));

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Main\Mail;

        use Sofy\Mail\Mailable;

        class $name extends Mailable
        {
            public function __construct(
                // public readonly mixed \$data,
            ) {}

            public function build(): static
            {
                return \$this
                    ->subject('Your subject here')
                    ->view('mail.$viewName');
            }
        }
        PHP;
    }
}
