<?php

declare(strict_types=1);

namespace Sofy\Storage;

class FileSystem implements DiskInterface
{
    private string $root;
    private ?string $baseUrl;

    public function __construct(string $root, ?string $baseUrl = null)
    {
        $this->root    = rtrim($root, '/');
        $this->baseUrl = $baseUrl ? rtrim($baseUrl, '/') : null;

        if (!is_dir($this->root)) {
            mkdir($this->root, 0755, recursive: true);
        }
    }

    public function put(string $path, string $contents): bool
    {
        $full = $this->full($path);
        $dir  = dirname($full);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }
        return file_put_contents($full, $contents) !== false;
    }

    public function get(string $path): ?string
    {
        $full = $this->full($path);
        return file_exists($full) ? (string) file_get_contents($full) : null;
    }

    public function exists(string $path): bool
    {
        return file_exists($this->full($path));
    }

    public function missing(string $path): bool
    {
        return !$this->exists($path);
    }

    public function delete(string|array $path): bool
    {
        $paths  = (array) $path;
        $result = true;
        foreach ($paths as $p) {
            $full = $this->full($p);
            if (file_exists($full)) {
                $result = unlink($full) && $result;
            }
        }
        return $result;
    }

    public function move(string $from, string $to): bool
    {
        $dest = $this->full($to);
        $dir  = dirname($dest);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }
        return rename($this->full($from), $dest);
    }

    public function copy(string $from, string $to): bool
    {
        $dest = $this->full($to);
        $dir  = dirname($dest);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }
        return copy($this->full($from), $dest);
    }

    public function size(string $path): int
    {
        return (int) filesize($this->full($path));
    }

    public function lastModified(string $path): int
    {
        return (int) filemtime($this->full($path));
    }

    public function mimeType(string $path): string|false
    {
        $full = $this->full($path);
        if (!file_exists($full)) {
            return false;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($full);
    }

    public function files(string $directory = ''): array
    {
        $dir = $this->full($directory);
        if (!is_dir($dir)) {
            return [];
        }
        return array_values(array_filter(
            (array) scandir($dir),
            fn($f) => $f !== '.' && $f !== '..' && is_file("$dir/$f"),
        ));
    }

    public function directories(string $directory = ''): array
    {
        $dir = $this->full($directory);
        if (!is_dir($dir)) {
            return [];
        }
        return array_values(array_filter(
            (array) scandir($dir),
            fn($f) => $f !== '.' && $f !== '..' && is_dir("$dir/$f"),
        ));
    }

    public function makeDirectory(string $path): bool
    {
        $full = $this->full($path);
        return is_dir($full) || mkdir($full, 0755, recursive: true);
    }

    public function deleteDirectory(string $path): bool
    {
        $full = $this->full($path);
        if (!is_dir($full)) {
            return false;
        }
        $this->wipeDirectory($full);
        return rmdir($full);
    }

    public function url(string $path): string
    {
        $path = ltrim($path, '/');
        if ($this->baseUrl !== null) {
            return $this->baseUrl . '/' . $path;
        }
        $base = function_exists('config') ? (string) config('app.url', '') : '';
        return rtrim($base, '/') . '/storage/' . $path;
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function full(string $path): string
    {
        return $this->root . '/' . ltrim($path, '/');
    }

    private function wipeDirectory(string $dir): void
    {
        foreach ((array) scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $sub = "$dir/$item";
            is_dir($sub) ? $this->wipeDirectory($sub) || rmdir($sub) : unlink($sub);
        }
    }
}
