<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Sofy\Core\Application;

/**
 * Update the framework code (src/, bootstrap/, sofy CLI) to the latest
 * release on Packagist — without touching the user's app code, .env,
 * storage/, modules/, database/, routes/, lang/, resources/, config/.
 *
 * Usage:
 *   php sofy update                        // latest stable
 *   php sofy update --version=0.1.2        // specific version
 *   php sofy update --dry-run              // show diff, change nothing
 *   php sofy update --no-migrate           // skip migrations
 *   php sofy update --allow-downgrade      // install an older version
 */
class UpdateCommand extends Command
{
    protected string $signature   = 'update {--version= : Target version (e.g. 0.1.2). Defaults to latest stable} {--dry-run : Show what would change without applying} {--no-migrate : Skip running migrations after update} {--allow-downgrade : Allow installing an older version} {--no-interaction : Skip the apply-changes confirmation (web-trigger path)} {--no-composer : Skip the post-update composer dump-autoload step}';
    protected string $description = 'Update the framework code (src/, bootstrap/, sofy CLI) to the latest version on Packagist';

    private const string PACKAGIST_URL = 'https://repo.packagist.org/p2/sofyphp/framework.json';
    private const string PACKAGE_NAME  = 'sofyphp/framework';

    /** @var list<string> Files/directories owned by the framework — safe to overwrite. */
    private const array TARGETS = ['src', 'bootstrap', 'sofy'];

    public function handle(): int
    {
        // Need a way to extract zips: ext-zip (preferred) or the `unzip` CLI.
        $hasExtZip = extension_loaded('zip');
        $hasUnzip  = trim((string) shell_exec('command -v unzip 2>/dev/null')) !== '';
        if (!$hasExtZip && !$hasUnzip) {
            $phpV = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
            $this->error('Need either ext-zip or the `unzip` CLI to extract the release archive.');
            $this->line("  sudo apt install php{$phpV}-zip   # PHP extension (preferred)");
            $this->line('  sudo apt install unzip            # …or the system tool');
            return 1;
        }

        $current = Application::version();
        $this->info("Current Sofy version: $current");

        $versions = $this->fetchPackagistVersions();
        if ($versions === null) {
            $this->error('Could not fetch package metadata from Packagist.');
            $this->line('  Check connectivity to ' . self::PACKAGIST_URL);
            return 1;
        }

        $requested = (string) ($this->option('version') ?: $this->latestStable($versions));
        if ($requested === '') {
            $this->error('No stable releases found on Packagist.');
            return 1;
        }

        // Allow user to type "0.1.2" or "v0.1.2" — Packagist stores tags as "v0.1.2".
        $resolved = $versions[$requested] ?? $versions['v' . $requested] ?? null;
        if ($resolved === null) {
            $this->error("Version $requested not found on Packagist.");
            $this->line('  Available: ' . implode(', ', array_keys($versions)));
            return 1;
        }

        $target   = (string) $resolved['version'];
        $currentN = ltrim($current, 'v');
        $targetN  = ltrim($target,  'v');

        if ($currentN === $targetN) {
            $this->success("Already on $current — nothing to do.");
            return 0;
        }

        if (!$this->option('allow-downgrade') && version_compare($targetN, $currentN, '<')) {
            $this->error("Target $target is older than current $current.");
            $this->line('  Pass --allow-downgrade if intentional.');
            return 1;
        }

        $this->info("Updating $current → $target");

        // ── Download dist archive ──
        $url = (string) ($resolved['dist']['url'] ?? '');
        if ($url === '') {
            $this->error('Package metadata has no dist URL.');
            return 1;
        }

        $tmpDir = sys_get_temp_dir() . '/sofy-update-' . bin2hex(random_bytes(4));
        $zipF   = $tmpDir . '.zip';

        $this->info('Downloading dist…');
        if (!$this->download($url, $zipF)) {
            $this->error('Download failed.');
            return 1;
        }

        // ── Extract ──
        $this->info('Extracting…');
        @mkdir($tmpDir, 0755, true);
        if ($hasExtZip) {
            $zip = new \ZipArchive();
            if ($zip->open($zipF) !== true) {
                $this->error("Could not open archive: $zipF");
                $this->cleanup($tmpDir, $zipF);
                return 1;
            }
            // Zip-slip defense-in-depth: reject traversal/absolute entries
            // BEFORE extractTo() touches disk.
            for ($i = 0, $n = $zip->numFiles; $i < $n; $i++) {
                $name = (string) $zip->getNameIndex($i);
                $norm = str_replace('\\', '/', $name);
                $bad  = $norm === '' || $norm === '.' || $norm === '..'
                    || str_starts_with($norm, '/')
                    || preg_match('#^[A-Za-z]:/#', $norm) === 1
                    || in_array('..', explode('/', $norm), true);
                if ($bad) {
                    $zip->close();
                    $this->error("Unsafe archive entry rejected: $name");
                    $this->cleanup($tmpDir, $zipF);
                    return 1;
                }
            }
            $zip->extractTo($tmpDir);
            $zip->close();
        } else {
            // Fallback to the system `unzip` tool. Lets `php sofy update`
            // bootstrap itself on a server that only has the bare php-cli.
            $code = $this->execLine('unzip -q ' . escapeshellarg($zipF) . ' -d ' . escapeshellarg($tmpDir));
            if ($code !== 0) {
                $this->error("unzip failed (exit $code) on archive: $zipF");
                $this->cleanup($tmpDir, $zipF);
                return 1;
            }
        }
        @unlink($zipF);

        // GitHub zipballs nest everything under one top-level dir.
        $newRoot = $this->findArchiveRoot($tmpDir);
        if ($newRoot === null) {
            $this->error('Could not find framework root in archive (no src/ directory found).');
            $this->cleanup($tmpDir);
            return 1;
        }

        // ── Diff ──
        $basePath = $this->basePath();
        $diff     = $this->collectDiff($newRoot, $basePath);

        $this->renderDiff($diff);

        if ($diff['total'] === 0) {
            $this->info('Framework code is already in sync with this release.');
            $this->cleanup($tmpDir);
            return 0;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run — no files were modified.');
            $this->cleanup($tmpDir);
            return 0;
        }

        // The /admin/system/update web trigger passes --no-interaction (and
        // already showed its own JS confirm); skip the CLI prompt in that case.
        if (!$this->option('no-interaction') && !$this->confirm("Apply these changes to $basePath?", true)) {
            $this->warn('Aborted.');
            $this->cleanup($tmpDir);
            return 0;
        }

        // ── Apply ──
        foreach (self::TARGETS as $t) {
            $src = $newRoot . '/' . $t;
            $dst = $basePath . '/' . $t;
            if (is_dir($src)) {
                $this->copyDir($src, $dst);
            } elseif (is_file($src)) {
                @copy($src, $dst);
                if ($t === 'sofy') {
                    @chmod($dst, 0755);
                }
            }
        }

        // Surgically update the `version` field in composer.json so that
        // Application::version() reflects the new release. We deliberately
        // don't overwrite composer.json wholesale — user customisations to
        // `require`, `autoload`, `scripts`, etc. are preserved.
        $this->bumpComposerVersion($basePath, ltrim($target, 'v'));

        $this->cleanup($tmpDir);

        // ── Post-update ──
        if ($this->option('no-composer')) {
            $this->comment('Skipping composer dump-autoload (--no-composer). Run `composer dump-autoload -o` manually.');
        } else {
            $this->info('Refreshing autoloader…');
            $this->execLine('composer dump-autoload --optimize --working-dir=' . escapeshellarg($basePath));
        }

        if (!$this->option('no-migrate')) {
            $this->info('Running migrations…');
            $this->execLine('php ' . escapeshellarg($basePath . '/sofy') . ' migrate');
        }

        $this->success("Updated to $target.");
        $this->comment('Note: composer.json was NOT modified. If this release added a new');
        $this->comment('Composer dependency, you may need to merge it manually.');
        return 0;
    }

    // ── Packagist / network ──────────────────────────────────────────────────

    /** @return array<string, array<string, mixed>>|null  version => entry, dev-* skipped */
    private function fetchPackagistVersions(): ?array
    {
        $ctx = stream_context_create([
            'http' => [
                'header'  => 'User-Agent: Sofy-Update/' . Application::version(),
                'timeout' => 30,
            ],
        ]);
        $body = @file_get_contents(self::PACKAGIST_URL, false, $ctx);
        if ($body === false) {
            return null;
        }
        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['packages'][self::PACKAGE_NAME])) {
            return null;
        }
        $out = [];
        foreach ($data['packages'][self::PACKAGE_NAME] as $entry) {
            $name = (string) ($entry['version'] ?? '');
            if ($name === '' || str_starts_with($name, 'dev-')) {
                continue;
            }
            $out[$name] = $entry;
        }
        return $out;
    }

    /** @param array<string, mixed> $versions */
    private function latestStable(array $versions): string
    {
        $names = array_keys($versions);
        usort($names, static fn(string $a, string $b): int => version_compare(ltrim($b, 'v'), ltrim($a, 'v')));
        return (string) ($names[0] ?? '');
    }

    private function download(string $url, string $dest): bool
    {
        $ch = curl_init($url);
        $fp = fopen($dest, 'w');
        if ($ch === false || $fp === false) {
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_USERAGENT      => 'Sofy-Update/' . Application::version(),
        ]);
        $ok   = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        if ($ok === false || $code !== 200) {
            @unlink($dest);
            return false;
        }
        return true;
    }

    // ── Diff & file ops ──────────────────────────────────────────────────────

    private function findArchiveRoot(string $dir): ?string
    {
        foreach ((glob($dir . '/*', GLOB_ONLYDIR) ?: []) as $entry) {
            if (is_dir($entry . '/src')) {
                return $entry;
            }
        }
        return null;
    }

    /** @return array{added: list<string>, modified: list<string>, removed: list<string>, total: int} */
    private function collectDiff(string $newRoot, string $basePath): array
    {
        $diff = ['added' => [], 'modified' => [], 'removed' => [], 'total' => 0];

        foreach (self::TARGETS as $t) {
            $src = "$newRoot/$t";
            $dst = "$basePath/$t";

            // File target (e.g. `sofy` CLI)
            if (is_file($src) || (is_file($dst) && !is_dir($src))) {
                if (!file_exists($dst)) {
                    $diff['added'][] = $t;
                } elseif (file_exists($src) && md5_file($src) !== md5_file($dst)) {
                    $diff['modified'][] = $t;
                }
                continue;
            }

            // Directory target
            $newFiles = is_dir($src) ? $this->relFiles($src) : [];
            $oldFiles = is_dir($dst) ? $this->relFiles($dst) : [];
            $newSet   = array_flip($newFiles);
            $oldSet   = array_flip($oldFiles);

            foreach ($newFiles as $f) {
                if (!isset($oldSet[$f])) {
                    $diff['added'][] = "$t/$f";
                } elseif (md5_file("$src/$f") !== md5_file("$dst/$f")) {
                    $diff['modified'][] = "$t/$f";
                }
            }
            foreach ($oldFiles as $f) {
                if (!isset($newSet[$f])) {
                    $diff['removed'][] = "$t/$f";
                }
            }
        }

        sort($diff['added']);
        sort($diff['modified']);
        sort($diff['removed']);

        $diff['total'] = count($diff['added']) + count($diff['modified']) + count($diff['removed']);
        return $diff;
    }

    /** @param array{added: list<string>, modified: list<string>, removed: list<string>, total: int} $diff */
    private function renderDiff(array $diff): void
    {
        if ($diff['total'] === 0) {
            return;
        }
        $this->line();
        $this->comment(sprintf(
            '  %d added · %d modified · %d removed',
            count($diff['added']), count($diff['modified']), count($diff['removed']),
        ));
        $this->line('  ' . str_repeat('─', 48));
        foreach ($diff['added']    as $f) $this->line("  \033[32m+\033[0m $f");
        foreach ($diff['modified'] as $f) $this->line("  \033[33m~\033[0m $f");
        foreach ($diff['removed']  as $f) $this->line("  \033[31m-\033[0m $f");
        $this->line();
    }

    /** @return list<string> */
    private function relFiles(string $dir): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );
        foreach ($it as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isFile()) {
                $files[] = substr($item->getPathname(), strlen($dir) + 1);
            }
        }
        sort($files);
        return $files;
    }

    private function copyDir(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            @mkdir($dst, 0755, true);
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($it as $item) {
            /** @var \SplFileInfo $item */
            $rel    = substr($item->getPathname(), strlen($src) + 1);
            $target = "$dst/$rel";
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    @mkdir($target, 0755, true);
                }
            } else {
                @copy($item->getPathname(), $target);
            }
        }
    }

    private function cleanup(string $dir, ?string $extraFile = null): void
    {
        if ($extraFile !== null) {
            @unlink($extraFile);
        }
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }

    private function bumpComposerVersion(string $basePath, string $newVersion): void
    {
        $path = $basePath . '/composer.json';
        if (!is_file($path)) {
            return;
        }
        $content = (string) file_get_contents($path);
        $patched = preg_replace(
            '/("version"\s*:\s*)"[^"]+"/',
            '$1' . json_encode($newVersion),
            $content,
            1,
        );
        if ($patched === null || $patched === $content) {
            return;
        }
        file_put_contents($path, $patched);
    }

    private function basePath(): string
    {
        if (function_exists('base_path')) {
            return base_path();
        }
        $cwd = getcwd();
        return $cwd !== false ? $cwd : '.';
    }

    private function execLine(string $cmd): int
    {
        passthru($cmd, $code);
        return $code;
    }
}
