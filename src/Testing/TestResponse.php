<?php

declare(strict_types=1);

namespace Sofy\Testing;

use Sofy\Http\Response;
use PHPUnit\Framework\Assert;

/** Wraps a Response with test assertion helpers. */
class TestResponse
{
    public function __construct(private readonly Response $response) {}

    public function getStatus(): int
    {
        return $this->response->getStatus();
    }

    public function getBody(): string
    {
        return $this->response->getBody();
    }

    public function json(): mixed
    {
        return json_decode($this->response->getBody(), true);
    }

    // ── Assertions ────────────────────────────────────────────────────────────

    public function assertStatus(int $status): static
    {
        Assert::assertSame($status, $this->response->getStatus(),
            "Expected status $status, got {$this->response->getStatus()}."
        );
        return $this;
    }

    public function assertOk(): static          { return $this->assertStatus(200); }
    public function assertCreated(): static     { return $this->assertStatus(201); }
    public function assertNoContent(): static   { return $this->assertStatus(204); }
    public function assertNotFound(): static    { return $this->assertStatus(404); }
    public function assertForbidden(): static   { return $this->assertStatus(403); }
    public function assertUnauthorized(): static { return $this->assertStatus(401); }
    public function assertRedirect(?string $to = null): static
    {
        Assert::assertTrue(
            in_array($this->response->getStatus(), [301, 302, 303, 307, 308], true),
            "Expected redirect, got status {$this->response->getStatus()}."
        );
        if ($to !== null) {
            Assert::assertSame($to, $this->response->getHeader('Location'));
        }
        return $this;
    }

    public function assertJson(array $data): static
    {
        $body = $this->json();
        foreach ($data as $key => $value) {
            Assert::assertArrayHasKey($key, (array) $body, "Response JSON missing key [$key].");
            Assert::assertSame($value, $body[$key]);
        }
        return $this;
    }

    public function assertJsonPath(string $path, mixed $expected): static
    {
        $data = $this->json();
        $segments = explode('.', $path);
        foreach ($segments as $segment) {
            $data = (array) $data;
            Assert::assertArrayHasKey($segment, $data, "JSON path [$path] not found.");
            $data = $data[$segment];
        }
        Assert::assertEquals($expected, $data);
        return $this;
    }

    public function assertSee(string $text): static
    {
        Assert::assertStringContainsString($text, $this->response->getBody());
        return $this;
    }

    public function assertDontSee(string $text): static
    {
        Assert::assertStringNotContainsString($text, $this->response->getBody());
        return $this;
    }

    public function assertHeader(string $name, ?string $value = null): static
    {
        $actual = $this->response->getHeader($name);
        Assert::assertNotNull($actual, "Response missing header [$name].");
        if ($value !== null) {
            Assert::assertSame($value, $actual, "Header [$name] expected [$value], got [$actual].");
        }
        return $this;
    }
}
