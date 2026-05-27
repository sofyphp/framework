<?php

declare(strict_types=1);

namespace Sofy\Testing\Fakes;

use Sofy\Storage\DiskInterface;
use Sofy\Storage\Storage;
use PHPUnit\Framework\Assert;

/**
 * In-memory storage fake for tests.
 *
 * Usage:
 *   $fake = StorageFake::swap();
 *   // ... code that calls Storage::put() ...
 *   $fake->assertExists('avatars/user1.jpg');
 */
class StorageFake implements DiskInterface
{
    /** @var array<string, string> path → contents */
    private array $files = [];

    public static function swap(string $disk = ''): static
    {
        $fake = new static();
        Storage::setDisk($fake, $disk);
        return $fake;
    }

    // ── DiskInterface ─────────────────────────────────────────────────────────

    public function put(string $path, string $contents): bool
    {
        $this->files[$path] = $contents;
        return true;
    }

    public function get(string $path): ?string
    {
        return $this->files[$path] ?? null;
    }

    public function exists(string $path): bool
    {
        return isset($this->files[$path]);
    }

    public function missing(string $path): bool
    {
        return !$this->exists($path);
    }

    public function delete(string|array $path): bool
    {
        foreach ((array) $path as $p) {
            unset($this->files[$p]);
        }
        return true;
    }

    public function move(string $from, string $to): bool
    {
        if (!isset($this->files[$from])) {
            return false;
        }
        $this->files[$to] = $this->files[$from];
        unset($this->files[$from]);
        return true;
    }

    public function copy(string $from, string $to): bool
    {
        if (!isset($this->files[$from])) {
            return false;
        }
        $this->files[$to] = $this->files[$from];
        return true;
    }

    public function size(string $path): int
    {
        return isset($this->files[$path]) ? strlen($this->files[$path]) : 0;
    }

    public function lastModified(string $path): int
    {
        return time();
    }

    public function mimeType(string $path): string|false
    {
        return 'application/octet-stream';
    }

    public function files(string $directory = ''): array
    {
        $prefix = $directory !== '' ? rtrim($directory, '/') . '/' : '';
        return array_values(array_filter(
            array_keys($this->files),
            fn($p) => str_starts_with($p, $prefix) && !str_contains(substr($p, strlen($prefix)), '/')
        ));
    }

    public function directories(string $directory = ''): array
    {
        $prefix = $directory !== '' ? rtrim($directory, '/') . '/' : '';
        $dirs   = [];
        foreach (array_keys($this->files) as $path) {
            if (!str_starts_with($path, $prefix)) {
                continue;
            }
            $relative = substr($path, strlen($prefix));
            $parts    = explode('/', $relative);
            if (count($parts) > 1) {
                $dirs[] = $prefix . $parts[0];
            }
        }
        return array_values(array_unique($dirs));
    }

    public function makeDirectory(string $path): bool
    {
        return true;
    }

    public function deleteDirectory(string $path): bool
    {
        $prefix = rtrim($path, '/') . '/';
        foreach (array_keys($this->files) as $file) {
            if (str_starts_with($file, $prefix)) {
                unset($this->files[$file]);
            }
        }
        return true;
    }

    public function url(string $path): string
    {
        return '/fake-storage/' . ltrim($path, '/');
    }

    // ── Assertions ────────────────────────────────────────────────────────────

    public function assertExists(string $path): void
    {
        Assert::assertArrayHasKey($path, $this->files, "Expected file [$path] to exist in storage.");
    }

    public function assertMissing(string $path): void
    {
        Assert::assertArrayNotHasKey($path, $this->files, "Expected file [$path] to be absent from storage.");
    }

    public function assertCount(int $count): void
    {
        Assert::assertCount($count, $this->files, "Expected $count file(s) in storage.");
    }

    public function allFiles(): array
    {
        return array_keys($this->files);
    }
}
