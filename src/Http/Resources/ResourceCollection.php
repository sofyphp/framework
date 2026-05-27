<?php

declare(strict_types=1);

namespace Sofy\Http\Resources;

use JsonException;
use JsonSerializable;
use Sofy\Http\Response;

class ResourceCollection implements JsonSerializable
{
    public static ?string $wrap = 'data';

    protected array $with    = [];
    protected array $headers = [];
    protected int   $status  = 200;

    /** @param class-string<JsonResource> $resourceClass */
    public function __construct(
        protected array  $items,
        protected string $resourceClass,
    ) {}

    // ── Transform ─────────────────────────────────────────────────────────────

    public function toArray(): array
    {
        return array_map(
            fn($item) => (new $this->resourceClass($item))->toArray(),
            $this->items
        );
    }

    public function with(array $data): static
    {
        $this->with = array_merge($this->with, $data);
        return $this;
    }

    // ── Response ──────────────────────────────────────────────────────────────

    public function withStatus(int $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function withHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /** @throws JsonException */
    public function toResponse(): Response
    {
        $data = $this->toArray();
        $wrap = $this->resourceClass::$wrap ?? static::$wrap;

        $body = $wrap
            ? array_merge([$wrap => $data], $this->with)
            : array_merge($data, $this->with);

        return Response::json($body, $this->status, $this->headers);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
