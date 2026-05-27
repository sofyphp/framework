<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class MakeResourceCommand extends Command
{
    protected string $signature   = 'make:resource {name} {--collection}';
    protected string $description = 'Create a new JSON resource class';

    public function handle(): int
    {
        $name = $this->argument('name');

        if ($name === null) {
            $this->error('Missing argument: name');
            return 1;
        }

        $name       = ucfirst((string) $name);
        $collection = (bool) $this->option('collection');

        if ($collection && !str_ends_with($name, 'Collection')) {
            $name .= 'Collection';
        }

        $dir = base_path('Main/Resources');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "$dir/$name.php";

        if (file_exists($path)) {
            $this->warn("Resource [$name] already exists.");
            return 1;
        }

        file_put_contents($path, $collection ? $this->collectionStub($name) : $this->resourceStub($name));

        $this->success("Resource [$name] created successfully.");
        $this->line("  → Main/Resources/$name.php");
        return 0;
    }

    private function resourceStub(string $name): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Main\Resources;

        use Sofy\Http\Resources\JsonResource;

        class $name extends JsonResource
        {
            public function toArray(): array
            {
                return [
                    'id' => \$this->id,
                ];
            }
        }
        PHP;
    }

    private function collectionStub(string $name): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Main\Resources;

        use Sofy\Http\Resources\ResourceCollection;

        class $name extends ResourceCollection
        {
            // Override toArray() to customise the collection envelope.
        }
        PHP;
    }
}
