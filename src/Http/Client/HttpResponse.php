<?php

declare(strict_types=1);

namespace Sofy\Http\Client;

use JsonException;
use RuntimeException;

readonly class HttpResponse
{
    public function __construct(
        private int    $status,
        private string $body,
        private array  $headers, // ['Header-Name' => 'value']
    ) {}

    public function body(): string
    {
        return $this->body;
    }

    /**
     * Декодирует JSON-ответ.
     * Если передан $key — возвращает значение по dot-нотации, иначе весь массив.
     *
     * @throws JsonException
     */
    public function json(?string $key = null): mixed
    {
        $data = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);

        if ($key === null) {
            return $data;
        }

        return array_reduce(
            explode('.', $key),
            static fn(mixed $carry, string $segment) => is_array($carry) ? ($carry[$segment] ?? null) : null,
            $data,
        );
    }

    public function status(): int
    {
        return $this->status;
    }

    /** 2xx */
    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function successful(): bool
    {
        return $this->ok();
    }

    /** >= 400 */
    public function failed(): bool
    {
        return $this->status >= 400;
    }

    /** 4xx */
    public function clientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    /** 5xx */
    public function serverError(): bool
    {
        return $this->status >= 500;
    }

    public function header(string $name): string
    {
        return $this->headers[$name] ?? $this->headers[strtolower($name)] ?? '';
    }

    public function headers(): array
    {
        return $this->headers;
    }

    /** Бросает RuntimeException, если статус >= 400. */
    public function throw(): static
    {
        if ($this->failed()) {
            throw new RuntimeException(
                "HTTP request failed with status $this->status: $this->body"
            );
        }
        return $this;
    }
}
