<?php

declare(strict_types=1);

namespace Sofy\Http\Resources;

use JsonSerializable;
use Sofy\Http\Response;

/**
 * API Resource transformer.
 *
 * Usage:
 *   class UserResource extends JsonResource
 *   {
 *       public function toArray(): array
 *       {
 *           return [
 *               'id'    => $this->resource->id,
 *               'name'  => $this->resource->name,
 *               'email' => $this->resource->email,
 *           ];
 *       }
 *   }
 *
 *   return UserResource::make($user)->toResponse();
 *   return UserResource::collection($users)->toResponse();
 */
abstract class JsonResource implements JsonSerializable
{
    /** Additional top-level wrapper key, e.g. 'data'. null = no wrapping. */
    public static ?string $wrap = 'data';

    protected array $with    = [];
    protected array $headers = [];
    protected int   $status  = 200;

    public function __construct(protected mixed $resource) {}

    public static function make(mixed $resource): static
    {
        return new static($resource);
    }

    public static function collection(array $resources): ResourceCollection
    {
        return new ResourceCollection($resources, static::class);
    }

    // ── Transform ─────────────────────────────────────────────────────────────

    /**
     * Override this to define the resource representation.
     */
    abstract public function toArray(): array;

    /**
     * Merge extra top-level keys into the response envelope.
     */
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

    public function toResponse(): Response
    {
        return Response::json($this->envelope(), $this->status, $this->headers);
    }

    protected function envelope(): array
    {
        $data = $this->toArray();

        $body = static::$wrap
            ? array_merge([static::$wrap => $data], $this->with)
            : array_merge($data, $this->with);

        return $body;
    }

    // ── JsonSerializable ──────────────────────────────────────────────────────

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    // ── Magic property shortcut ───────────────────────────────────────────────

    public function __get(string $key): mixed
    {
        if (is_array($this->resource)) {
            return $this->resource[$key] ?? null;
        }
        return is_object($this->resource) ? ($this->resource->$key ?? null) : null;
    }
}
