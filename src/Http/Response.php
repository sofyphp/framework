<?php

declare(strict_types=1);

namespace Sofy\Http;

use Sofy\View\View;
use RuntimeException;

class Response
{
    public function __construct(
        private string $content = '',
        private int    $status  = 200,
        private array  $headers = [],
    ) {
        $this->headers = array_merge(
            ['Content-Type' => 'text/html; charset=UTF-8'],
            $headers
        );
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): static
    {
        return new static(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $status,
            array_merge(['Content-Type' => 'application/json; charset=UTF-8'], $headers)
        );
    }

    public static function redirect(string $url, int $status = 302): static
    {
        return new static('', $status, ['Location' => $url]);
    }

    public static function view(string $template, array $data = [], int $status = 200): static
    {
        return new static(View::make($template, $data), $status);
    }

    public function withHeader(string $name, string $value): static
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function withStatus(int $status): static
    {
        $clone          = clone $this;
        $clone->status  = $status;
        return $clone;
    }

    public function withContent(string $content): static
    {
        $clone          = clone $this;
        $clone->content = $content;
        return $clone;
    }

    // ── File responses ────────────────────────────────────────────────────────

    public static function download(string $path, ?string $name = null): static
    {
        if (!file_exists($path)) {
            throw new RuntimeException("File not found: $path");
        }
        $name = $name ?? basename($path);
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        return new static((string) file_get_contents($path), 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'attachment; filename="' . addslashes($name) . '"',
            'Content-Length'      => (string) filesize($path),
        ]);
    }

    public static function file(string $path, ?string $mime = null): static
    {
        if (!file_exists($path)) {
            throw new RuntimeException("File not found: $path");
        }
        $mime = $mime ?? mime_content_type($path) ?: 'application/octet-stream';
        return new static((string) file_get_contents($path), 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="' . addslashes(basename($path)) . '"',
            'Content-Length'      => (string) filesize($path),
        ]);
    }

    // ── Flash redirect ────────────────────────────────────────────────────────

    public function with(string $key, mixed $value): static
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['_flash_new'][$key] = $value;
        return $this;
    }

    public function withInput(array $input = []): static
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['_old_input'] = $input;
        return $this;
    }

    public function withErrors(array $errors): static
    {
        return $this->with('errors', $errors);
    }

    public function send(): void
    {
        Cookie::sendQueued();
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    if (is_array($v)) {
                        Cookie::send($v);
                    } else {
                        header("$name: $v", false);
                    }
                }
            } else {
                header("$name: $value");
            }
        }
        echo $this->content;
    }

    public function getStatus(): int     { return $this->status; }
    public function getContent(): string { return $this->content; }
    public function getBody(): string    { return $this->content; }
    public function getHeaders(): array  { return $this->headers; }
    public function getHeader(string $name): ?string { return $this->headers[$name] ?? null; }

    public function withCookie(array $cookie): static
    {
        $clone = clone $this;
        $clone->headers['Set-Cookie'][] = $cookie;
        return $clone;
    }
}
