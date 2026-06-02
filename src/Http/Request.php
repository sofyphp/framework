<?php

declare(strict_types=1);

namespace Sofy\Http;

use Sofy\Http\UploadedFile;

class Request
{
    private array   $routeParams = [];
    private ?array  $jsonBody    = null;
    private ?string $cachedPath  = null;  // parse_url() result — immutable after capture
    private ?array  $cachedAll   = null;  // merged input — invalidated on setRouteParams()

    public function __construct(
        private readonly array $query,
        private readonly array $body,
        private readonly array $server,
        private readonly array $files,
        private readonly array $cookies,
    ) {}

    public static function capture(): static
    {
        return new static($_GET, $_POST, $_SERVER, $_FILES, $_COOKIE);
    }

    public function method(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST') {
            $override = $this->body['_method']
                ?? $this->server['HTTP_X_HTTP_METHOD_OVERRIDE']
                ?? null;
            if ($override) {
                return strtoupper($override);
            }
        }

        return $method;
    }

    public function uri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    public function path(): string
    {
        if ($this->cachedPath === null) {
            $raw = parse_url($this->uri(), PHP_URL_PATH) ?? '/';
            $this->cachedPath = '/' . trim($raw, '/');
        }
        return $this->cachedPath;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function all(): array
    {
        if ($this->cachedAll !== null) {
            return $this->cachedAll;
        }
        $base = $this->isJson()
            ? array_merge($this->query, $this->json() ?? [])
            : array_merge($this->query, $this->body);
        return $this->cachedAll = array_merge($base, $this->routeParams);
    }

    public function only(string ...$keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function except(string ...$keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);
        return $value !== null && $value !== '';
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->jsonBody === null) {
            $raw = file_get_contents('php://input');
            $this->jsonBody = json_decode($raw ?: '', true) ?? [];
        }
        return $key === null ? $this->jsonBody : ($this->jsonBody[$key] ?? $default);
    }

    public function file(string $key): UploadedFile|array|null
    {
        $entry = $this->files[$key] ?? null;
        if ($entry === null) return null;
        return UploadedFile::fromFilesArray($this->files, $key);
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $this->server[$normalized] ?? $default;
    }

    public function isJson(): bool
    {
        return str_contains($this->server['CONTENT_TYPE'] ?? '', 'application/json');
    }

    public function isAjax(): bool
    {
        return ($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    /**
     * Whether the request came in over HTTPS. Checks the standard server
     * vars plus the reverse-proxy `X-Forwarded-Proto` so it works behind
     * nginx/Caddy doing TLS termination.
     */
    public function isHttps(): bool
    {
        $https = (string) ($this->server['HTTPS'] ?? '');
        if ($https !== '' && strtolower($https) !== 'off') {
            return true;
        }
        if (((string) ($this->server['SERVER_PORT'] ?? '')) === '443') {
            return true;
        }
        $forwarded = strtolower((string) ($this->server['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $forwarded === 'https';
    }

    public function ip(): string
    {
        return $this->server['HTTP_X_FORWARDED_FOR']
            ?? $this->server['REMOTE_ADDR']
            ?? '127.0.0.1';
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    public function bearerToken(): ?string
    {
        $header = $this->server['HTTP_AUTHORIZATION'] ?? '';
        return str_starts_with($header, 'Bearer ')
            ? substr($header, 7)
            : null;
    }

    /**
     * Валидирует входящие данные и возвращает проверенные поля.
     *
     * @throws \Sofy\Validation\ValidationException
     */
    public function validate(array $rules): array
    {
        return \Sofy\Validation\Validator::make($this->all(), $rules)->validate();
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
        $this->cachedAll   = null; // invalidate merged input cache
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }
}
