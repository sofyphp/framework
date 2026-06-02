<?php

declare(strict_types=1);

namespace Sofy\Module\Marketplace;

use Sofy\Core\Application;

/**
 * Installer for marketplace modules. Resolves a catalog entry → downloads
 * its zip → extracts → moves to modules/{Name}/ → patches composer.json
 * psr-4 → asks composer to dump autoload → optionally runs migrations.
 *
 * Mirrors the careful subprocess + pre-flight discipline of
 * \Sofy\Admin\Controllers\UpdateController so a click from /admin doesn't
 * leave the app in a half-installed state when permissions or binaries
 * are missing.
 *
 * Results are returned as InstallResult records — never throws, so admin
 * controllers and CLI commands render the same outcome consistently.
 */
class Installer
{
    public function __construct(
        private readonly Catalog $catalog = new Catalog(),
    ) {}

    // ── Install ─────────────────────────────────────────────────────────────

    public function install(string $slug, bool $runMigrations = true): InstallResult
    {
        $entry = $this->catalog->find($slug);
        if ($entry === null) {
            return InstallResult::failure("Module '{$slug}' not found in the catalog.");
        }
        if (!empty($entry['installed'])) {
            return InstallResult::failure("Module '{$slug}' is already installed (modules/" . ($entry['name'] ?? $slug) . ').');
        }

        $name      = (string) ($entry['name'] ?? '');
        $namespace = (string) ($entry['namespace'] ?? '');
        if ($name === '' || $namespace === '') {
            return InstallResult::failure("Catalog entry for '{$slug}' is missing required fields (name, namespace).");
        }

        // Pre-flight checks — bail clearly before any download.
        $problems = $this->preflightChecks($name);
        if ($problems !== []) {
            return InstallResult::failure('Pre-flight failed:' . "\n  • " . implode("\n  • ", $problems));
        }

        $dist = is_array($entry['dist'] ?? null) ? $entry['dist'] : null;
        if ($dist === null) {
            return InstallResult::failure("Catalog entry for '{$slug}' has no `dist` block.");
        }
        $zipUrl = $this->resolveDistUrl($dist);
        if ($zipUrl === null) {
            return InstallResult::failure("Could not resolve a download URL from the `dist` block (type='" . (string) ($dist['type'] ?? '') . "').");
        }

        $log = [];
        $log[] = "Source: {$zipUrl}";

        // ── Download ──
        $tmpZip = sys_get_temp_dir() . '/sofy-module-' . bin2hex(random_bytes(4)) . '.zip';
        if (!$this->download($zipUrl, $tmpZip)) {
            return InstallResult::failure("Download failed: {$zipUrl}", $log);
        }
        $log[] = 'Downloaded → ' . $tmpZip;

        // ── Extract ──
        $tmpDir = $tmpZip . '-extract';
        @mkdir($tmpDir, 0755, true);
        if (!$this->extractZip($tmpZip, $tmpDir)) {
            @unlink($tmpZip);
            return InstallResult::failure('Could not extract archive (need ext-zip or `unzip` CLI).', $log);
        }
        @unlink($tmpZip);
        $log[] = 'Extracted';

        // ── Locate module source inside the archive ──
        $source = $this->findModuleSource($tmpDir, $name, (string) ($dist['subdir'] ?? ''));
        if ($source === null) {
            $this->rmrf($tmpDir);
            return InstallResult::failure(
                'Could not find the module folder inside the archive. '
                . 'Expected sofy-module.json or a ' . $name . '.php class at the archive root '
                . ($dist['subdir'] ?? false ? "(or under subdir '{$dist['subdir']}')." : '.'),
                $log,
            );
        }
        $log[] = 'Module root: ' . substr($source, strlen($tmpDir) + 1);

        // ── Copy into modules/{Name}/ ──
        $target = $this->basePath() . '/modules/' . $name;
        if (is_dir($target) && !$this->isDirEmpty($target)) {
            $this->rmrf($tmpDir);
            return InstallResult::failure("modules/{$name}/ already exists. Uninstall it first.", $log);
        }
        @mkdir($target, 0755, true);
        $this->copyDir($source, $target);
        $this->rmrf($tmpDir);
        $log[] = "Copied to modules/{$name}/";

        // ── Patch composer.json psr-4 ──
        if (!$this->ensureComposerNamespace($name, $namespace)) {
            $log[] = 'WARN: composer.json psr-4 unchanged (entry already present or composer.json missing).';
        } else {
            $log[] = "composer.json psr-4 ← {$namespace} → modules/{$name}/";
        }

        // ── Dump autoload via composer ──
        $composer = $this->findBinary('composer');
        if ($composer !== null) {
            [$exit, $out] = $this->runProcess(
                [$composer, 'dump-autoload', '--optimize', '--working-dir=' . $this->basePath()],
                $this->basePath(),
                120,
            );
            $log[] = 'composer dump-autoload → exit ' . $exit;
            if ($exit !== 0) $log[] = trim($out);
        } else {
            $log[] = 'WARN: composer not on PATH — run `composer dump-autoload -o` from a shell.';
        }

        // ── Add to the module enable-list ──
        // ModuleLoader (since v0.4.10) only loads modules that are explicitly
        // enabled — installation isn't complete until the registry knows.
        $loader = \Sofy\Core\Application::getInstance()->getModuleLoader();
        if ($loader->enable($name)) {
            $log[] = "Enabled in {$loader->registryPath()}";
        } else {
            $log[] = 'Already enabled.';
        }

        // ── Optional migrations ──
        if ($runMigrations) {
            $migrated = $this->runMigrations();
            $log[] = 'php sofy migrate → ' . ($migrated === true ? 'ok' : ('skipped: ' . $migrated));
        }

        return InstallResult::success("Module '{$slug}' installed as modules/{$name}/.", $log);
    }

    public function uninstall(string $slug): InstallResult
    {
        $entry = $this->catalog->find($slug);
        if ($entry === null || empty($entry['installed'])) {
            return InstallResult::failure("Module '{$slug}' is not installed.");
        }

        $name      = (string) ($entry['name'] ?? $slug);
        $namespace = (string) ($entry['namespace'] ?? '');
        $target    = $this->basePath() . '/modules/' . $name;
        $log       = [];

        if (!is_dir($target)) {
            return InstallResult::failure("modules/{$name}/ does not exist.");
        }
        if (!is_writable($target)) {
            return InstallResult::failure("modules/{$name}/ is not writable by the web user.");
        }

        // Disable in the enable-list FIRST — once we drop the folder a stale
        // entry would just produce a "module not found" warning on every boot.
        $loader = \Sofy\Core\Application::getInstance()->getModuleLoader();
        if ($loader->disable($name)) {
            $log[] = "Disabled in {$loader->registryPath()}";
        }

        $this->rmrf($target);
        $log[] = "Removed modules/{$name}/";

        if ($namespace !== '') {
            $this->removeComposerNamespace($namespace);
            $log[] = "Cleaned psr-4 entry: {$namespace}";
        }

        $composer = $this->findBinary('composer');
        if ($composer !== null) {
            [$exit, ] = $this->runProcess(
                [$composer, 'dump-autoload', '--optimize', '--working-dir=' . $this->basePath()],
                $this->basePath(),
                120,
            );
            $log[] = 'composer dump-autoload → exit ' . $exit;
        } else {
            $log[] = 'WARN: composer not on PATH — run `composer dump-autoload -o` from a shell.';
        }

        $this->catalog->refresh();
        return InstallResult::success("Module '{$slug}' uninstalled.", $log);
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /** @return list<string> */
    private function preflightChecks(string $name): array
    {
        $base = $this->basePath();
        $problems = [];
        foreach (['modules', 'composer.json'] as $rel) {
            $path = $base . '/' . $rel;
            if (!file_exists($path)) {
                $problems[] = "missing: {$rel}";
                continue;
            }
            if (!is_writable($path)) {
                $problems[] = "not writable: {$rel}";
            }
        }
        $target = $base . '/modules/' . $name;
        if (is_dir($target) && !$this->isDirEmpty($target)) {
            $problems[] = "already exists: modules/{$name}/";
        }
        return $problems;
    }

    /**
     * Translate the manifest `dist` block into a concrete download URL.
     * Supports three formats:
     *   - github-release  → latest release source-zip
     *   - github-tag      → specific tag archive
     *   - zip             → direct URL
     */
    private function resolveDistUrl(array $dist): ?string
    {
        $type = (string) ($dist['type'] ?? '');
        $repo = (string) ($dist['repo'] ?? '');
        $url  = (string) ($dist['url']  ?? '');
        $tag  = (string) ($dist['tag']  ?? '');

        return match ($type) {
            'zip'            => $url !== '' ? $url : null,
            'github-tag'     => $repo !== '' && $tag !== ''
                ? "https://github.com/{$repo}/archive/refs/tags/{$tag}.zip"
                : null,
            'github-release' => $repo !== ''
                ? $this->latestGithubReleaseZip($repo)
                : null,
            default          => null,
        };
    }

    private function latestGithubReleaseZip(string $repo): ?string
    {
        $body = $this->httpGet("https://api.github.com/repos/{$repo}/releases/latest", [
            'Accept: application/vnd.github+json',
        ]);
        if ($body === null) return null;
        $data = json_decode($body, true);
        $tag  = is_array($data) ? (string) ($data['tag_name'] ?? '') : '';
        return $tag !== '' ? "https://github.com/{$repo}/archive/refs/tags/{$tag}.zip" : null;
    }

    /**
     * Walk the extracted archive to find the module folder. Modern GitHub
     * archives nest under one top-level dir; if the manifest declared a
     * `subdir`, we descend into it from there. The folder is identified
     * by either a sofy-module.json or a {Name}.php Module class.
     */
    private function findModuleSource(string $extractRoot, string $expectedName, string $subdir): ?string
    {
        // Single top-level dir from GitHub archive
        $tops = glob($extractRoot . '/*', GLOB_ONLYDIR) ?: [];
        $candidates = [$extractRoot, ...$tops];

        if ($subdir !== '') {
            foreach ($candidates as $base) {
                $path = $base . '/' . trim($subdir, '/');
                if (is_dir($path)) {
                    return $path;
                }
            }
        }

        foreach ($candidates as $path) {
            if (is_file($path . '/sofy-module.json') || is_file($path . '/' . $expectedName . '.php')) {
                return $path;
            }
        }
        return null;
    }

    private function download(string $url, string $dest): bool
    {
        $ch = curl_init($url);
        $fp = fopen($dest, 'w');
        if ($ch === false || $fp === false) return false;
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_USERAGENT      => 'Sofy-Marketplace/' . Application::version(),
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

    private function extractZip(string $zipFile, string $dest): bool
    {
        if (extension_loaded('zip')) {
            $zip = new \ZipArchive();
            if ($zip->open($zipFile) !== true) return false;

            // Zip-slip defense-in-depth: iterate entries, reject anything
            // with traversal or absolute paths BEFORE extractTo() touches
            // disk. PHP ≥7.4's extractTo normalises some of these, but
            // edge cases with symlinks and Windows paths remain.
            for ($i = 0, $n = $zip->numFiles; $i < $n; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if ($this->isUnsafeArchivePath($name)) {
                    $zip->close();
                    return false;
                }
            }

            $zip->extractTo($dest);
            $zip->close();
            return true;
        }
        $unzip = $this->findBinary('unzip');
        if ($unzip === null) return false;
        [$exit, ] = $this->runProcess([$unzip, '-q', $zipFile, '-d', $dest], $dest, 60);
        return $exit === 0;
    }

    private function ensureComposerNamespace(string $name, string $namespace): bool
    {
        $path = $this->basePath() . '/composer.json';
        if (!is_file($path)) return false;
        $content = (string) file_get_contents($path);
        $data    = json_decode($content, true);
        if (!is_array($data)) return false;

        $psr4 = $data['autoload']['psr-4'] ?? [];
        if (isset($psr4[$namespace])) return false;

        $psr4[$namespace] = 'modules/' . $name . '/';
        ksort($psr4);
        $data['autoload']['psr-4'] = $psr4;

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) return false;
        file_put_contents($path, $json . "\n");
        return true;
    }

    private function removeComposerNamespace(string $namespace): void
    {
        $path = $this->basePath() . '/composer.json';
        if (!is_file($path)) return;
        $content = (string) file_get_contents($path);
        $data    = json_decode($content, true);
        if (!is_array($data)) return;
        $psr4 = $data['autoload']['psr-4'] ?? [];
        if (!isset($psr4[$namespace])) return;
        unset($psr4[$namespace]);
        $data['autoload']['psr-4'] = $psr4;
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json !== false) file_put_contents($path, $json . "\n");
    }

    private function runMigrations(): true|string
    {
        $php = $this->findBinary('php');
        if ($php === null) return 'php binary not on PATH';
        [$exit, $out] = $this->runProcess(
            [$php, $this->basePath() . '/sofy', 'migrate'],
            $this->basePath(),
            180,
        );
        return $exit === 0 ? true : ('migrate exit ' . $exit . ': ' . trim($out));
    }

    private function findBinary(string $name): ?string
    {
        $paths = ['/usr/local/bin', '/usr/bin', '/bin', '/opt/homebrew/bin', '/opt/local/bin'];
        $env   = getenv('PATH');
        $search = $env ? explode(PATH_SEPARATOR, $env) : [];
        foreach (array_merge($paths, $search) as $dir) {
            $cand = rtrim($dir, '/') . '/' . $name;
            if (is_file($cand) && is_executable($cand)) return $cand;
        }
        if ($name === 'php' && defined('PHP_BINARY') && PHP_BINARY !== '' && is_executable(PHP_BINARY)) {
            $b = basename(PHP_BINARY);
            if ($b === 'php' || preg_match('/^php\d/', $b)) return PHP_BINARY;
        }
        return null;
    }

    /**
     * @param  list<string> $argv
     * @return array{0:int, 1:string}
     */
    private function runProcess(array $argv, string $cwd, int $timeoutSeconds): array
    {
        $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $env  = [
            'PATH' => implode(PATH_SEPARATOR, array_unique(array_merge(
                ['/usr/local/bin', '/usr/bin', '/bin', '/opt/homebrew/bin', '/opt/local/bin'],
                explode(PATH_SEPARATOR, (string) getenv('PATH')),
            ))),
            'HOME'   => (string) (getenv('HOME') ?: sys_get_temp_dir()),
            'TMPDIR' => sys_get_temp_dir(),
            'LANG'   => 'C.UTF-8',
            'COMPOSER_HOME' => (string) (getenv('COMPOSER_HOME') ?: (getenv('HOME') ?: sys_get_temp_dir()) . '/.composer'),
        ];
        $proc = proc_open($argv, $spec, $pipes, $cwd, $env);
        if (!is_resource($proc)) return [127, 'Could not start: ' . implode(' ', $argv)];
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = '';
        $deadline = microtime(true) + $timeoutSeconds;
        $status = ['running' => true, 'exitcode' => -1];
        while (true) {
            $status = proc_get_status($proc);
            $out   .= (string) stream_get_contents($pipes[1]);
            $out   .= (string) stream_get_contents($pipes[2]);
            if (!$status['running']) break;
            if (microtime(true) > $deadline) {
                proc_terminate($proc, 9);
                $out .= "\n[timeout]\n";
                break;
            }
            usleep(100_000);
        }
        $out .= (string) stream_get_contents($pipes[1]);
        $out .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $exit = proc_close($proc);
        if ($exit === -1 && isset($status['exitcode'])) $exit = (int) $status['exitcode'];
        return [$exit, $out];
    }

    /** @param list<string> $headers */
    private function httpGet(string $url, array $headers = []): ?string
    {
        $ch = curl_init($url);
        if ($ch === false) return null;
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'Sofy-Marketplace/' . Application::version(),
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code !== 200) return null;
        return (string) $body;
    }

    private function copyDir(string $src, string $dst): void
    {
        if (!is_dir($dst)) @mkdir($dst, 0755, true);
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($it as $item) {
            /** @var \SplFileInfo $item */
            $rel    = substr($item->getPathname(), strlen($src) + 1);
            $target = "{$dst}/{$rel}";
            if ($item->isDir()) {
                if (!is_dir($target)) @mkdir($target, 0755, true);
            } else {
                @copy($item->getPathname(), $target);
            }
        }
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }

    private function isDirEmpty(string $dir): bool
    {
        $entries = @scandir($dir);
        if ($entries === false) return true;
        return count(array_diff($entries, ['.', '..'])) === 0;
    }

    private function basePath(): string
    {
        return function_exists('base_path') ? base_path() : (string) (getcwd() ?: '.');
    }

    /**
     * Tightens defense around ZipArchive::extractTo against zip-slip:
     * archive entries that escape the extraction root via '../', leading
     * '/', or Windows-style 'C:\…' paths.
     */
    private function isUnsafeArchivePath(string $name): bool
    {
        $norm = str_replace('\\', '/', $name);
        if ($norm === '' || $norm === '.' || $norm === '..') return true;
        if (str_starts_with($norm, '/')) return true;
        if (preg_match('#^[A-Za-z]:/#', $norm) === 1) return true;
        foreach (explode('/', $norm) as $segment) {
            if ($segment === '..') return true;
        }
        return false;
    }
}
