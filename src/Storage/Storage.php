<?php

declare(strict_types=1);

namespace Sofy\Storage;

/**
 * Storage facade.
 *
 * Usage:
 *   Storage::put('avatars/user1.jpg', $contents);
 *   Storage::disk('s3')->url('avatars/user1.jpg');
 *   Storage::disk('public')->files('images/');
 */
class Storage
{
    /** @var array<string, DiskInterface> */
    private static array $disks = [];

    // ── Disk resolution ───────────────────────────────────────────────────────

    public static function disk(string $disk = ''): DiskInterface
    {
        if ($disk === '') {
            $disk = (string) (function_exists('config')
                ? config('storage.default', 'local')
                : 'local');
        }

        if (!isset(self::$disks[$disk])) {
            self::$disks[$disk] = self::resolve($disk);
        }

        return self::$disks[$disk];
    }

    /** Override a disk — used by StorageFake in tests. */
    public static function setDisk(DiskInterface $disk, string $name = ''): void
    {
        self::$disks[$name !== '' ? $name : (string) (function_exists('config') ? config('storage.default', 'local') : 'local')] = $disk;
    }

    private static function resolve(string $name): DiskInterface
    {
        $cfg = function_exists('config')
            ? (array) config("storage.disks.$name", [])
            : [];

        $driver = $cfg['driver'] ?? 'local';

        if ($driver === 's3') {
            return new S3Driver($cfg);
        }

        // local driver
        $root = $cfg['root'] ?? (sys_get_temp_dir() . '/sofy/' . $name);
        $url  = $cfg['url']  ?? null;

        return new FileSystem($root, $url ?: null);
    }

    // ── Shortcuts (default disk) ───────────────────────────────────────────────

    public static function put(string $path, string $contents): bool
    {
        return static::disk()->put($path, $contents);
    }

    public static function get(string $path): ?string
    {
        return static::disk()->get($path);
    }

    public static function exists(string $path): bool
    {
        return static::disk()->exists($path);
    }

    public static function missing(string $path): bool
    {
        return static::disk()->missing($path);
    }

    public static function delete(string|array $path): bool
    {
        return static::disk()->delete($path);
    }

    public static function move(string $from, string $to): bool
    {
        return static::disk()->move($from, $to);
    }

    public static function copy(string $from, string $to): bool
    {
        return static::disk()->copy($from, $to);
    }

    public static function size(string $path): int
    {
        return static::disk()->size($path);
    }

    public static function lastModified(string $path): int
    {
        return static::disk()->lastModified($path);
    }

    public static function mimeType(string $path): string|false
    {
        return static::disk()->mimeType($path);
    }

    public static function files(string $directory = ''): array
    {
        return static::disk()->files($directory);
    }

    public static function directories(string $directory = ''): array
    {
        return static::disk()->directories($directory);
    }

    public static function makeDirectory(string $path): bool
    {
        return static::disk()->makeDirectory($path);
    }

    public static function deleteDirectory(string $path): bool
    {
        return static::disk()->deleteDirectory($path);
    }

    /** Public URL via the 'public' disk (convenience shortcut). */
    public static function url(string $path): string
    {
        return static::disk('public')->url($path);
    }
}
