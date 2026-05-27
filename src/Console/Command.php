<?php

declare(strict_types=1);

namespace Sofy\Console;

abstract class Command
{
    protected string $signature   = '';
    protected string $description = '';

    private array  $arguments = [];
    private array  $options   = [];
    private Output $output;

    public function __construct()
    {
        $this->output = new Output();
    }

    abstract public function handle(): int;

    // ── Called by Kernel before handle() ─────────────────────────────────────

    public function getSignature(): string   { return $this->signature; }
    public function getDescription(): string { return $this->description; }

    public function setArguments(array $arguments): void { $this->arguments = $arguments; }
    public function setOptions(array $options): void     { $this->options   = $options; }

    // ── API for subclasses ────────────────────────────────────────────────────

    protected function argument(string $name, mixed $default = null): mixed
    {
        return $this->arguments[$name] ?? $default;
    }

    protected function option(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    protected function info(string $message): void    { $this->output->info($message); }
    protected function error(string $message): void   { $this->output->error($message); }
    protected function warn(string $message): void    { $this->output->warn($message); }
    protected function line(string $message = ''): void { $this->output->line($message); }
    protected function comment(string $message): void { $this->output->comment($message); }
    protected function success(string $message): void { $this->output->success($message); }

    protected function ask(string $question): string
    {
        return $this->output->ask($question);
    }

    protected function confirm(string $question, bool $default = false): bool
    {
        return $this->output->confirm($question, $default);
    }

    protected function select(string $question, array $options, string|int|null $default = null): string|int
    {
        return $this->output->select($question, $options, $default);
    }

    protected function stepHeader(int $step, int $total, string $title): void
    {
        $this->output->stepHeader($step, $total, $title);
    }

    protected function table(array $headers, array $rows): void
    {
        $this->output->table($headers, $rows);
    }
}
