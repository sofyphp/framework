<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Sofy\Module\Marketplace\Catalog;

/**
 * List every module the marketplace can see: remote catalog + locally
 * installed modules under modules/. Optional --search narrows the list.
 *
 *   php sofy marketplace:list
 *   php sofy marketplace:list --search=orders
 *   php sofy marketplace:list --installed
 */
class MarketplaceListCommand extends Command
{
    protected string $signature   = 'marketplace:list {--search= : Filter by name/slug/description/tags} {--installed : Show only installed modules}';
    protected string $description = 'List modules known to the Sofy marketplace (catalog + on disk)';

    public function handle(): int
    {
        $catalog = new Catalog();
        $modules = $catalog->all();

        if ($this->option('installed')) {
            $modules = array_values(array_filter($modules, static fn(array $m): bool => !empty($m['installed'])));
        }
        $search = (string) ($this->option('search') ?? '');
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $modules = array_values(array_filter($modules, static function (array $m) use ($needle): bool {
                $hay = mb_strtolower(implode(' ', [
                    (string) ($m['name']        ?? ''),
                    (string) ($m['slug']        ?? ''),
                    (string) ($m['description'] ?? ''),
                    implode(' ', (array) ($m['tags'] ?? [])),
                ]));
                return str_contains($hay, $needle);
            }));
        }

        if (empty($modules)) {
            $this->warn('No modules match.');
            return 0;
        }

        $this->info('Modules (' . count($modules) . '):');
        $this->line();

        foreach ($modules as $m) {
            $slug      = (string) ($m['slug']        ?? '?');
            $name      = (string) ($m['name']        ?? '?');
            $version   = (string) ($m['version']     ?? '?');
            $author    = (string) ($m['author']      ?? '—');
            $desc      = (string) ($m['description'] ?? '');
            $installed = !empty($m['installed']);

            $marker = $installed ? "\033[32m●\033[0m" : "\033[90m○\033[0m";
            $this->line("  {$marker} \033[1m{$name}\033[0m  \033[90mv{$version}\033[0m  \033[2m{$slug}\033[0m");
            if ($desc !== '') $this->line('     ' . $desc);
            $this->line("     \033[90mby {$author}" . ($installed ? '  · installed' : '') . "\033[0m");
            $this->line();
        }

        return 0;
    }
}
