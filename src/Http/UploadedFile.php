<?php

declare(strict_types=1);

namespace Sofy\Http;

/**
 * Wrapper around a single uploaded file from $_FILES.
 *
 * Usage:
 *   $file = $request->file('avatar');
 *   if ($file?->isValid()) {
 *       $path = $file->store('avatars');    // → 'avatars/abc123.jpg'
 *       $file->move(storage_path('avatars'), 'custom.jpg');
 *   }
 */
class UploadedFile
{
    public function __construct(
        private readonly string $originalName,
        private readonly string $mimeType,
        private readonly string $tmpPath,
        private readonly int    $error,
        private readonly int    $size,
    ) {}

    public static function fromArray(array $file): static
    {
        return new static(
            $file['name']     ?? '',
            $file['type']     ?? 'application/octet-stream',
            $file['tmp_name'] ?? '',
            $file['error']    ?? UPLOAD_ERR_NO_FILE,
            $file['size']     ?? 0,
        );
    }

    /**
     * Build from $_FILES[$key] — handles both flat and multi-file arrays.
     *
     * @return static|static[]|null
     */
    public static function fromFilesArray(array $files, string $key): static|array|null
    {
        $entry = $files[$key] ?? null;
        if ($entry === null) return null;

        // Multiple file input (<input name="photos[]">)
        if (is_array($entry['name'])) {
            $result = [];
            foreach (array_keys($entry['name']) as $i) {
                $result[] = new static(
                    $entry['name'][$i],
                    $entry['type'][$i],
                    $entry['tmp_name'][$i],
                    $entry['error'][$i],
                    $entry['size'][$i],
                );
            }
            return $result;
        }

        return static::fromArray($entry);
    }

    // ── Metadata ──────────────────────────────────────────────────────────────

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && is_uploaded_file($this->tmpPath);
    }

    public function getOriginalName(): string { return $this->originalName; }
    public function getMimeType(): string     { return $this->mimeType; }
    public function getSize(): int            { return $this->size; }
    public function getError(): int           { return $this->error; }

    public function getClientOriginalExtension(): string
    {
        return strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
    }

    public function guessExtension(): string
    {
        return match ($this->mimeType) {
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/gif'       => 'gif',
            'image/webp'      => 'webp',
            'image/svg+xml'   => 'svg',
            'application/pdf' => 'pdf',
            'text/plain'      => 'txt',
            'text/csv'        => 'csv',
            default           => $this->getClientOriginalExtension(),
        };
    }

    // ── Moving / storing ──────────────────────────────────────────────────────

    /**
     * Move the uploaded file to $directory under $filename.
     * Returns the full destination path.
     */
    public function move(string $directory, string $filename = ''): string
    {
        if (!$this->isValid()) {
            throw new \RuntimeException('Invalid or missing uploaded file (error: ' . $this->error . ').');
        }

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = $filename ?: $this->originalName;
        $dest     = rtrim($directory, '/') . '/' . $filename;

        if (!move_uploaded_file($this->tmpPath, $dest)) {
            throw new \RuntimeException("Could not move uploaded file to [$dest].");
        }

        return $dest;
    }

    /**
     * Store to storage/app/{$path} with a random unique filename.
     * Returns the relative path (e.g. 'avatars/abc123.jpg').
     */
    public function store(string $path = '', string $disk = ''): string
    {
        $filename = bin2hex(random_bytes(16)) . '.' . $this->guessExtension();
        $baseDir  = function_exists('storage_path') ? storage_path('app') : sys_get_temp_dir();
        $dir      = $baseDir . ($path ? '/' . trim($path, '/') : '');

        $this->move($dir, $filename);

        return ($path ? trim($path, '/') . '/' : '') . $filename;
    }

    public function storeAs(string $path, string $filename, string $disk = ''): string
    {
        $baseDir = function_exists('storage_path') ? storage_path('app') : sys_get_temp_dir();
        $dir     = $baseDir . ($path ? '/' . trim($path, '/') : '');

        $this->move($dir, $filename);

        return ($path ? trim($path, '/') . '/' : '') . $filename;
    }

    public function getContent(): string
    {
        return (string) file_get_contents($this->tmpPath);
    }
}
