<?php

declare(strict_types=1);

namespace Sofy\Support;

use RuntimeException;

readonly class Dotenv
{
    public function __construct(private string $path) {}

    public static function load(string $path): static
    {
        $instance = new static($path);
        $instance->parse();
        return $instance;
    }

    public static function loadSafe(string $path): static
    {
        $instance = new static($path);
        if (file_exists($path)) {
            $instance->parse();
        }
        return $instance;
    }

    private function parse(): void
    {
        $lines = file($this->path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Handle export prefix
            if (str_starts_with($line, 'export ')) {
                $line = ltrim(substr($line, 7));
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$rawKey, $rawValue] = explode('=', $line, 2);
            $key   = trim($rawKey);
            $value = $this->resolveValue(trim($rawValue));

            $this->setVariable($key, $value);
        }
    }

    private function resolveValue(string $value): string
    {
        // Strip inline comment (unquoted)
        if (!str_starts_with($value, '"') && !str_starts_with($value, "'")) {
            if (($pos = strpos($value, ' #')) !== false) {
                $value = substr($value, 0, $pos);
            }
            $value = trim($value);
        }

        // Double-quoted: escape sequences + variable interpolation
        if (str_starts_with($value, '"') && str_ends_with($value, '"') && strlen($value) >= 2) {
            $value = substr($value, 1, -1);
            $value = stripcslashes($value);
            return preg_replace_callback('/\$\{([^}]+)}/', function ($m) {
                return $_ENV[$m[1]] ?? getenv($m[1]) ?: '';
            }, $value) ?? $value;
        }

        // Single-quoted: literal
        if (str_starts_with($value, "'") && str_ends_with($value, "'") && strlen($value) >= 2) {
            return substr($value, 1, -1);
        }

        // Unquoted special values
        return match (strtolower($value)) {
            'true', '(true)'   => 'true',
            'false', '(false)' => 'false',
            'null', '(null)', 'empty', '(empty)' => '',
            default            => $value,
        };
    }

    private function setVariable(string $key, string $value): void
    {
        if (array_key_exists($key, $_ENV)) {
            return; // don't overwrite existing
        }
        $_ENV[$key] = $value;
        putenv("$key=$value");
        $_SERVER[$key] = $value;
    }

    /**
     * Required variables — throw if any are missing or empty.
     *
     */
    public function required(string ...$keys): static
    {
        $missing = [];
        foreach ($keys as $key) {
            $value = $_ENV[$key] ?? getenv($key);
            if ($value === false || $value === null || $value === '') {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            throw new RuntimeException(
                'Missing required .env variables: ' . implode(', ', $missing)
            );
        }

        return $this;
    }
}
