<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class MakeMiddlewareCommand extends Command
{
    protected string $signature   = 'make:middleware {name}';
    protected string $description = 'Create a new middleware class';

    public function handle(): int
    {
        $name = $this->argument('name');

        if ($name === null) {
            $this->error('Missing argument: name');
            return 1;
        }

        $name = ucfirst((string) $name);
        if (!str_ends_with($name, 'Middleware')) {
            $name .= 'Middleware';
        }

        $path = base_path("Main/Middleware/$name.php");

        if (file_exists($path)) {
            $this->warn("Middleware [$name] already exists.");
            return 1;
        }

        file_put_contents($path, $this->stub($name));

        $this->success("Middleware [$name] created successfully.");
        $this->line("  → Main/Middleware/$name.php");
        return 0;
    }

    private function stub(string $name): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Main\Middleware;

        use Sofy\Http\Middleware\MiddlewareInterface;
        use Sofy\Http\Request;
        use Sofy\Http\Response;

        class $name implements MiddlewareInterface
        {
            public function handle(Request \$request, callable \$next): Response
            {
                // Before the request is handled...

                \$response = \$next(\$request);

                // After the response is built...

                return \$response;
            }
        }
        PHP;
    }
}
