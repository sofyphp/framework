<?php

declare(strict_types=1);

namespace Sofy\Module\Marketplace;

use Sofy\Cache\Cache;
use Sofy\Core\Application;

/**
 * Catalog reader for the Sofy module marketplace.
 *
 * The catalog is a JSON document listing module manifests
 * ({"modules": [...]}). The reader fetches the remote URL configured in
 * `config('marketplace.catalog_url')` (cached 1 hour) and falls back to
 * the bundled docs/marketplace.json when the remote is unreachable —
 * so the marketplace page is useful even on an air-gapped install or
 * before the central catalog has shipped.
 *
 * In parallel, the reader inspects modules/* on disk to know which
 * entries are already installed; the admin UI merges the two lists.
 */
class Catalog
{
    private const string CACHE_KEY = 'sofy.marketplace.catalog';
    private const int    CACHE_TTL = 3600;

    /**
     * Returns the union of remote/bundled catalog entries + manifests
     * detected on disk under modules/. Each entry is annotated with
     * `installed` (bool) and `installed_version` (?string) for the UI.
     *
     * @return list<array<string,mixed>>
     */
    public function all(): array
    {
        $catalog  = $this->fetch();
        $onDisk   = $this->localManifests();

        $bySlug = [];
        foreach ($catalog as $entry) {
            $slug = (string) ($entry['slug'] ?? '');
            if ($slug === '') continue;
            $bySlug[$slug] = $entry + ['installed' => false, 'installed_version' => null];
        }

        // Merge in modules detected on disk — installed ones might not be
        // in the remote catalog (e.g. a fork or a private module).
        foreach ($onDisk as $slug => $manifest) {
            $existing = $bySlug[$slug] ?? [];
            $bySlug[$slug] = $manifest + $existing + [
                'installed'         => true,
                'installed_version' => (string) ($manifest['version'] ?? '?'),
            ];
            $bySlug[$slug]['installed']         = true;
            $bySlug[$slug]['installed_version'] = (string) ($manifest['version'] ?? '?');
        }

        $list = array_values($bySlug);
        usort($list, static fn(array $a, array $b): int => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
        return $list;
    }

    public function find(string $slug): ?array
    {
        foreach ($this->all() as $entry) {
            if (($entry['slug'] ?? '') === $slug) {
                return $entry;
            }
        }
        return null;
    }

    /** Bust the remote-catalog cache. */
    public function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    // ── Sources ──────────────────────────────────────────────────────────────

    /**
     * Pull the catalog from the configured remote URL with a 1-hour cache.
     * Falls back to the bundled docs/marketplace.json on any failure.
     *
     * @return list<array<string,mixed>>
     */
    private function fetch(): array
    {
        $cached = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $url = (string) (function_exists('config') ? config('marketplace.catalog_url', '') : '');
            if ($url === '') {
                return $this->bundled();
            }
            $body = $this->httpGet($url);
            if ($body === null) return $this->bundled();
            $data = json_decode($body, true);
            $list = is_array($data) && isset($data['modules']) && is_array($data['modules'])
                ? array_values(array_filter($data['modules'], 'is_array'))
                : [];
            // Merge bundled with remote, remote wins on slug collision.
            return $this->mergeBySlug($this->bundled(), $list);
        });
        return is_array($cached) ? $cached : [];
    }

    /** @return list<array<string,mixed>> */
    private function bundled(): array
    {
        $path = $this->basePath() . '/docs/marketplace.json';
        if (!is_file($path)) return [];
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || !isset($data['modules']) || !is_array($data['modules'])) return [];
        return array_values(array_filter($data['modules'], 'is_array'));
    }

    /**
     * @param  list<array<string,mixed>> $a
     * @param  list<array<string,mixed>> $b
     * @return list<array<string,mixed>>
     */
    private function mergeBySlug(array $a, array $b): array
    {
        $out = [];
        foreach ($a as $entry) {
            $slug = (string) ($entry['slug'] ?? '');
            if ($slug !== '') $out[$slug] = $entry;
        }
        foreach ($b as $entry) {
            $slug = (string) ($entry['slug'] ?? '');
            if ($slug !== '') $out[$slug] = $entry;
        }
        return array_values($out);
    }

    /**
     * Read sofy-module.json from every directory under modules/.
     * Falls back to a minimal "guess from folder name" manifest for
     * legacy modules (Blog/Demo/Shop) that don't ship a manifest yet.
     *
     * @return array<string, array<string,mixed>>  slug => manifest
     */
    private function localManifests(): array
    {
        $dir = $this->basePath() . '/modules';
        if (!is_dir($dir)) return [];

        $out = [];
        foreach ((glob($dir . '/*', GLOB_ONLYDIR) ?: []) as $modDir) {
            $folder   = basename($modDir);
            $manifest = $this->readManifest($modDir);
            if ($manifest === null) {
                // Legacy module without a manifest — synthesise a stub so
                // it still shows up in the marketplace as "installed".
                $manifest = [
                    'slug'        => strtolower($folder),
                    'name'        => $folder,
                    'namespace'   => $folder . '\\',
                    'version'     => '—',
                    'description' => 'Установлено локально без sofy-module.json.',
                    'author'      => '—',
                    'categories'  => [],
                    'requires'    => [],
                    'dist'        => null,
                ];
            }
            $slug = (string) ($manifest['slug'] ?? strtolower($folder));
            $out[$slug] = $manifest;
        }
        return $out;
    }

    private function readManifest(string $moduleDir): ?array
    {
        $path = $moduleDir . '/sofy-module.json';
        if (!is_file($path)) return null;
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    private function httpGet(string $url): ?string
    {
        $ch = curl_init($url);
        if ($ch === false) return null;
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_USERAGENT      => 'Sofy-Marketplace/' . Application::version(),
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code !== 200) return null;
        return (string) $body;
    }

    private function basePath(): string
    {
        return function_exists('base_path') ? base_path() : (string) (getcwd() ?: '.');
    }
}
