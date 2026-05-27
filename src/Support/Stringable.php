<?php

declare(strict_types=1);

namespace Sofy\Support;

use Stringable as StringableInterface;

/**
 * Fluent string wrapper returned by Str::of().
 *
 * Usage:
 *   Str::of('hello world')
 *       ->title()
 *       ->slug()
 *       ->toString();  // 'hello-world'
 */
class Stringable implements StringableInterface
{
    public function __construct(private string $value) {}

    // ── Proxy all Str methods ─────────────────────────────────────────────────

    public function camel(): static         { return new static(Str::camel($this->value)); }
    public function studly(): static        { return new static(Str::studly($this->value)); }
    public function snake(string $d = '_'): static { return new static(Str::snake($this->value, $d)); }
    public function kebab(): static         { return new static(Str::kebab($this->value)); }
    public function upper(): static         { return new static(Str::upper($this->value)); }
    public function lower(): static         { return new static(Str::lower($this->value)); }
    public function title(): static         { return new static(Str::title($this->value)); }
    public function slug(string $sep = '-'): static { return new static(Str::slug($this->value, $sep)); }
    public function reverse(): static       { return new static(Str::reverse($this->value)); }
    public function squish(): static        { return new static(Str::squish($this->value)); }
    public function trim(string $chars = " \t\n\r\0\x0B"): static { return new static(trim($this->value, $chars)); }
    public function ltrim(string $chars = " \t\n\r\0\x0B"): static { return new static(ltrim($this->value, $chars)); }
    public function rtrim(string $chars = " \t\n\r\0\x0B"): static { return new static(rtrim($this->value, $chars)); }

    public function limit(int $limit = 100, string $end = '...'): static
    {
        return new static(Str::limit($this->value, $limit, $end));
    }

    public function words(int $words = 100, string $end = '...'): static
    {
        return new static(Str::words($this->value, $words, $end));
    }

    public function mask(string $char = '*', int $index = 0, ?int $length = null): static
    {
        return new static(Str::mask($this->value, $char, $index, $length));
    }

    public function replace(string|array $search, string|array $replace): static
    {
        return new static(Str::replace($this->value, $search, $replace));
    }

    public function remove(string|array $search): static
    {
        return new static(Str::remove($this->value, $search));
    }

    public function before(string $needle): static  { return new static(Str::before($this->value, $needle)); }
    public function after(string $needle): static   { return new static(Str::after($this->value, $needle)); }
    public function beforeLast(string $needle): static { return new static(Str::beforeLast($this->value, $needle)); }
    public function afterLast(string $needle): static  { return new static(Str::afterLast($this->value, $needle)); }

    public function between(string $from, string $to): static
    {
        return new static(Str::between($this->value, $from, $to));
    }

    public function padLeft(int $length, string $pad = ' '): static
    {
        return new static(Str::padLeft($this->value, $length, $pad));
    }

    public function padRight(int $length, string $pad = ' '): static
    {
        return new static(Str::padRight($this->value, $length, $pad));
    }

    public function wrap(string $before, string $after = ''): static
    {
        return new static(Str::wrap($this->value, $before, $after));
    }

    public function repeat(int $times): static
    {
        return new static(Str::repeat($this->value, $times));
    }

    public function plural(int $count = 2): static  { return new static(Str::plural($this->value, $count)); }
    public function singular(): static              { return new static(Str::singular($this->value)); }

    public function append(string ...$values): static
    {
        return new static($this->value . implode('', $values));
    }

    public function prepend(string ...$values): static
    {
        return new static(implode('', $values) . $this->value);
    }

    public function when(bool $condition, callable $callback): static
    {
        return $condition ? $callback($this) : $this;
    }

    public function unless(bool $condition, callable $callback): static
    {
        return !$condition ? $callback($this) : $this;
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function contains(string|array $needles): bool    { return Str::contains($this->value, $needles); }
    public function startsWith(string|array $needles): bool  { return Str::startsWith($this->value, $needles); }
    public function endsWith(string|array $needles): bool    { return Str::endsWith($this->value, $needles); }
    public function isEmpty(): bool   { return $this->value === ''; }
    public function isNotEmpty(): bool { return $this->value !== ''; }
    public function isJson(): bool    { return Str::isJson($this->value); }
    public function isEmail(): bool   { return Str::isEmail($this->value); }
    public function isUrl(): bool     { return Str::isUrl($this->value); }
    public function length(): int     { return Str::length($this->value); }
    public function wordCount(): int  { return Str::wordCount($this->value); }

    // ── Terminate ─────────────────────────────────────────────────────────────

    public function toString(): string { return $this->value; }
    public function __toString(): string { return $this->value; }
    public function dump(): static { var_dump($this->value); return $this; }
}
