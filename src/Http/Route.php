<?php

declare(strict_types=1);

namespace Sofy\Http;

class Route
{
    private string $pattern;
    private array  $paramNames = [];
    private ?string $name = null;

    public function __construct(
        private readonly array  $methods,
        private readonly string $path,
        private readonly mixed  $action,   // array|string|Closure — callable не разрешён как тип свойства
        private array           $middleware = [],
    ) {
        $this->compilePattern();
    }

    private function compilePattern(): void
    {
        $path = '/' . trim($this->path, '/');

        // Collect param names from {param} or :param syntax
        preg_match_all('/\{(\w+)\}|:(\w+)/', $path, $m);
        foreach (array_filter(array_merge($m[1], $m[2])) as $name) {
            $this->paramNames[] = $name;
        }

        // Replace placeholders with regex capture groups
        $pattern = preg_replace('/\{(\w+)\}|:(\w+)/', '([^/]+)', $path);
        $this->pattern = '#^' . $pattern . '$#u';
    }

    public function matches(string $path): bool
    {
        return (bool) preg_match($this->pattern, '/' . trim($path, '/'));
    }

    public function extractParams(string $path): array
    {
        preg_match($this->pattern, '/' . trim($path, '/'), $matches);
        array_shift($matches);
        return $this->paramNames
            ? array_combine($this->paramNames, $matches)
            : [];
    }

    public function middleware(array|string $middleware): static
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);
        return $this;
    }

    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function buildUrl(array $params = []): string
    {
        $url = $this->path;
        foreach ($params as $key => $value) {
            $url = str_replace(['{' . $key . '}', ':' . $key], (string) $value, $url);
        }
        return $url;
    }

    public function getMiddleware(): array        { return $this->middleware; }
    public function getAction(): array|string|callable { return $this->action; }
    public function getMethods(): array           { return $this->methods; }
    public function getPath(): string             { return $this->path; }
    public function getName(): ?string            { return $this->name; }
}
