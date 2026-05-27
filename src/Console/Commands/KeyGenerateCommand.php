<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Sofy\Console\Output;
use Sofy\Security\Crypt;

class KeyGenerateCommand extends Command
{
    protected string $signature    = 'key:generate';
    protected string $description  = 'Generate a new APP_KEY and write it to .env';

    public function handle(): int
    {
        $key     = Crypt::generateKey();
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            Output::error('.env file not found. Copy .env.example first.');
            return 1;
        }

        $content = file_get_contents($envPath);

        if (str_contains($content, 'APP_KEY=')) {
            $content = preg_replace('/^APP_KEY=.*/m', 'APP_KEY=' . $key, $content);
        } else {
            $content .= PHP_EOL . 'APP_KEY=' . $key;
        }

        file_put_contents($envPath, $content);

        Output::success('Application key set: ' . $key);
        return 0;
    }
}
