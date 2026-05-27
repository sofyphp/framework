<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class MakeControllerCommand extends Command
{
    protected string $signature   = 'make:controller {name}';
    protected string $description = 'Create a new controller class';

    public function handle(): int
    {
        $name = $this->argument('name');

        if ($name === null) {
            $this->error('Missing argument: name');
            return 1;
        }

        $name = ucfirst((string) $name);
        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }

        $path = base_path("Main/Controllers/$name.php");

        if (file_exists($path)) {
            $this->warn("Controller [$name] already exists.");
            return 1;
        }

        file_put_contents($path, $this->stub($name));

        $this->success("Controller [$name] created successfully.");
        $this->line("  → Main/Controllers/$name.php");
        return 0;
    }

    private function stub(string $name): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Main\Controllers;

        use Sofy\Http\Request;
        use Sofy\Http\Response;

        class $name extends Controller
        {
            public function index(Request \$request): Response
            {
                return \$this->view('...');
            }
        }
        PHP;
    }
}
