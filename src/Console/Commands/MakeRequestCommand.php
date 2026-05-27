<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class MakeRequestCommand extends Command
{
    protected string $signature   = 'make:request {name}';
    protected string $description = 'Create a new form request class';

    public function handle(): int
    {
        $name = $this->argument('name');

        if ($name === null) {
            $this->error('Missing argument: name');
            return 1;
        }

        $name = ucfirst((string) $name);
        if (!str_ends_with($name, 'Request')) {
            $name .= 'Request';
        }

        $dir = base_path('Main/Requests');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "$dir/$name.php";

        if (file_exists($path)) {
            $this->warn("Request [$name] already exists.");
            return 1;
        }

        file_put_contents($path, $this->stub($name));

        $this->success("Request [$name] created successfully.");
        $this->line("  → Main/Requests/$name.php");
        return 0;
    }

    private function stub(string $name): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Main\Requests;

        use Sofy\Http\FormRequest;

        class $name extends FormRequest
        {
            public function authorize(): bool
            {
                return true;
            }

            public function rules(): array
            {
                return [
                    // 'field' => 'required|string',
                ];
            }
        }
        PHP;
    }
}
