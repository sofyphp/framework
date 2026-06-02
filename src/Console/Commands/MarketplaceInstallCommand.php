<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Sofy\Module\Marketplace\Installer;

/**
 * CLI counterpart to the /admin/system/marketplace install button.
 *
 *   php sofy marketplace:install orders            # bring it down + wire it up
 *   php sofy marketplace:install orders --no-migrate
 */
class MarketplaceInstallCommand extends Command
{
    protected string $signature   = 'marketplace:install {slug : Catalog slug, e.g. orders or vendor/name} {--no-migrate : Skip running migrations after install}';
    protected string $description = 'Install a module from the Sofy marketplace catalog';

    public function handle(): int
    {
        $slug = trim((string) ($this->argument('slug') ?? ''));
        if ($slug === '') {
            $this->error('Slug is required: php sofy marketplace:install <slug>');
            return 1;
        }

        $this->info("Installing {$slug}…");

        $result = (new Installer())->install($slug, runMigrations: !$this->option('no-migrate'));

        foreach ($result->log as $line) {
            $this->line('  ' . $line);
        }
        $this->line();

        if ($result->ok) {
            $this->success($result->message);
            return 0;
        }
        $this->error($result->message);
        return 1;
    }
}
