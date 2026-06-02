<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Sofy\Module\Marketplace\Installer;

/**
 * CLI uninstall — symmetric counterpart to marketplace:install.
 *
 *   php sofy marketplace:uninstall orders
 */
class MarketplaceUninstallCommand extends Command
{
    protected string $signature   = 'marketplace:uninstall {slug : Catalog slug to remove, e.g. orders}';
    protected string $description = 'Uninstall a marketplace module (deletes modules/{Name}/ and cleans composer psr-4)';

    public function handle(): int
    {
        $slug = trim((string) ($this->argument('slug') ?? ''));
        if ($slug === '') {
            $this->error('Slug is required: php sofy marketplace:uninstall <slug>');
            return 1;
        }

        if (!$this->confirm("Remove module '{$slug}'? This deletes its folder and updates composer.json.", false)) {
            $this->warn('Aborted.');
            return 0;
        }

        $result = (new Installer())->uninstall($slug);

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
