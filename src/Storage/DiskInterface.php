<?php

declare(strict_types=1);

namespace Sofy\Storage;

interface DiskInterface
{
    /** Write content to a file. */
    public function put(string $path, string $contents): bool;

    /** Read a file. Returns null if the file does not exist. */
    public function get(string $path): ?string;

    public function exists(string $path): bool;
    public function missing(string $path): bool;

    /** Delete one file or an array of files. */
    public function delete(string|array $path): bool;

    public function move(string $from, string $to): bool;
    public function copy(string $from, string $to): bool;

    /** File size in bytes. */
    public function size(string $path): int;

    /** Unix timestamp of last modification. */
    public function lastModified(string $path): int;

    /** MIME type, or false on failure. */
    public function mimeType(string $path): string|false;

    /** List files in a directory (non-recursive). */
    public function files(string $directory = ''): array;

    /** List sub-directories in a directory. */
    public function directories(string $directory = ''): array;

    public function makeDirectory(string $path): bool;
    public function deleteDirectory(string $path): bool;

    /** Public URL to access the file. */
    public function url(string $path): string;
}
