<?php

declare(strict_types=1);

namespace Sofy\Console\Schedule;

class Event
{
    private string $expression  = '* * * * *';
    private ?string $label      = null;

    public function __construct(
        // PHP forbids `callable` as a property type — use Closure (the common
        // case; a command string is the other). Was `string|callable`, which
        // made this class fatal on load since the initial commit.
        private readonly string|\Closure $command,
    ) {}

    // ── Cron expression ───────────────────────────────────────────────────────

    public function cron(string $expression): static
    {
        $this->expression = $expression;
        return $this;
    }

    // ── Shortcuts ─────────────────────────────────────────────────────────────

    public function everyMinute(): static         { return $this->cron('* * * * *'); }
    public function everyFiveMinutes(): static    { return $this->cron('*/5 * * * *'); }
    public function everyTenMinutes(): static     { return $this->cron('*/10 * * * *'); }
    public function everyFifteenMinutes(): static { return $this->cron('*/15 * * * *'); }
    public function everyThirtyMinutes(): static  { return $this->cron('*/30 * * * *'); }

    public function hourly(): static { return $this->cron('0 * * * *'); }
    public function hourlyAt(int $minute): static { return $this->cron("$minute * * * *"); }

    public function daily(): static { return $this->cron('0 0 * * *'); }

    public function dailyAt(string $time): static
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');
        return $this->cron("$minute $hour * * *");
    }

    public function weekly(): static { return $this->cron('0 0 * * 0'); }

    public function weeklyOn(int $weekday, string $time = '0:0'): static
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');
        return $this->cron("$minute $hour * * $weekday");
    }

    public function monthly(): static { return $this->cron('0 0 1 * *'); }

    public function monthlyOn(int $day, string $time = '0:0'): static
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');
        return $this->cron("$minute $hour $day * *");
    }

    public function yearly(): static { return $this->cron('0 0 1 1 *'); }

    public function description(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    // ── Runtime ───────────────────────────────────────────────────────────────

    public function isDue(): bool
    {
        return $this->matchesCron($this->expression);
    }

    public function run(): void
    {
        if (is_callable($this->command)) {
            ($this->command)();
        } else {
            passthru($this->command);
        }
    }

    public function getExpression(): string { return $this->expression; }

    public function getSummary(): string
    {
        $cmd = is_callable($this->command) ? 'Closure' : $this->command;
        return ($this->label ?? $cmd) . '   [' . $this->expression . ']';
    }

    // ── Cron matching ─────────────────────────────────────────────────────────

    private function matchesCron(string $expression): bool
    {
        $parts = explode(' ', $expression);
        $now   = getdate();

        return $this->matchesPart($parts[0], $now['minutes'])
            && $this->matchesPart($parts[1], $now['hours'])
            && $this->matchesPart($parts[2], $now['mday'])
            && $this->matchesPart($parts[3], $now['mon'])
            && $this->matchesPart($parts[4], $now['wday']);
    }

    private function matchesPart(string $part, int $value): bool
    {
        if ($part === '*') {
            return true;
        }

        if (str_starts_with($part, '*/')) {
            $step = (int) substr($part, 2);
            return $step > 0 && $value % $step === 0;
        }

        if (str_contains($part, ',')) {
            return in_array($value, array_map('intval', explode(',', $part)), true);
        }

        if (str_contains($part, '-')) {
            [$from, $to] = explode('-', $part, 2);
            return $value >= (int) $from && $value <= (int) $to;
        }

        return (int) $part === $value;
    }
}
