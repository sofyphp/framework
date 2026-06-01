<?php

declare(strict_types=1);

namespace Sofy\Console;

class Output
{
    public function line(string $message = ''): void
    {
        echo $message . PHP_EOL;
    }

    public function info(string $message): void
    {
        echo "\033[32m$message\033[0m" . PHP_EOL;
    }

    public function error(string $message): void
    {
        echo "\033[31m$message\033[0m" . PHP_EOL;
    }

    public function warn(string $message): void
    {
        echo "\033[33m$message\033[0m" . PHP_EOL;
    }

    public function comment(string $message): void
    {
        echo "\033[36m$message\033[0m" . PHP_EOL;
    }

    public function success(string $message): void
    {
        echo "\033[1;32m✓ $message\033[0m" . PHP_EOL;
    }

    public function ask(string $question): string
    {
        echo "\033[33m$question\033[0m ";
        return trim(fgets(STDIN) ?: '');
    }

    public function confirm(string $question, bool $default = false): bool
    {
        return $this->isInteractive()
            ? $this->confirmInteractive($question, $default)
            : $this->confirmFallback($question, $default);
    }

    /**
     * Arrow-key navigated single-select. Falls back to a numbered text prompt
     * on non-interactive streams (CI, pipes, NO_INTERACTION=1, Windows).
     *
     * @param  array<string|int, string> $options  ['key' => 'Label', ...]
     */
    public function select(string $question, array $options, string|int|null $default = null): string|int
    {
        if (empty($options)) {
            throw new \InvalidArgumentException('select() requires at least one option');
        }

        if ($default === null) {
            $default = array_key_first($options);
        }

        return $this->isInteractive()
            ? $this->selectInteractive($question, $options, $default)
            : $this->selectFallback($question, $options, $default);
    }

    // ── Interactive prompts (TTY-only, raw mode) ──────────────────────────────

    private function isInteractive(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $cached = false;
        if (PHP_OS_FAMILY === 'Windows') return $cached;
        if (getenv('NO_INTERACTION') !== false) return $cached;
        if (getenv('CI') !== false) return $cached;
        if (!function_exists('stream_isatty')) return $cached;
        if (!@stream_isatty(STDIN) || !@stream_isatty(STDOUT)) return $cached;
        if (trim((string) shell_exec('command -v stty 2>/dev/null')) === '') return $cached;
        $cached = true;
        return $cached;
    }

    private function selectInteractive(string $question, array $options, string|int $default): string|int
    {
        $keys   = array_keys($options);
        $count  = count($keys);
        $cursor = array_search($default, $keys, true);
        if ($cursor === false) {
            $cursor = 0;
        }

        $restore = $this->enterRawMode();
        if ($restore === null) {
            return $this->selectFallback($question, $options, $default);
        }
        echo "\033[?25l"; // hide cursor

        echo PHP_EOL . "\033[1;33m? \033[0m\033[1m$question\033[0m" . PHP_EOL;
        echo "  \033[2m↑/↓ move · Enter select · Esc cancel\033[0m" . PHP_EOL;
        echo PHP_EOL;

        $rendered = false;
        while (true) {
            if ($rendered) {
                // Move cursor up `count` lines and clear each before redrawing.
                for ($i = 0; $i < $count; $i++) {
                    echo "\033[1A\033[2K";
                }
            }
            foreach ($keys as $i => $key) {
                $label = $options[$key];
                echo $i === $cursor
                    ? "  \033[1;36m▶\033[0m \033[1;37m$label\033[0m" . PHP_EOL
                    : "    \033[2m$label\033[0m" . PHP_EOL;
            }
            $rendered = true;

            switch ($this->readKey()) {
                case 'UP':     $cursor = ($cursor - 1 + $count) % $count; break;
                case 'DOWN':   $cursor = ($cursor + 1) % $count; break;
                case 'HOME':   $cursor = 0; break;
                case 'END':    $cursor = $count - 1; break;
                case 'ENTER':
                    $restore();
                    echo "\033[?25h"; // show cursor
                    return $keys[$cursor];
                case 'CANCEL':
                    $restore();
                    echo "\033[?25h\033[31mCancelled.\033[0m" . PHP_EOL;
                    exit(130);
            }
        }
    }

    private function selectFallback(string $question, array $options, string|int $default): string|int
    {
        $keys       = array_keys($options);
        $defaultIdx = array_search($default, $keys, true);
        $defaultNum = (($defaultIdx === false ? 0 : (int) $defaultIdx) + 1);

        echo PHP_EOL . "\033[33m$question\033[0m" . PHP_EOL;
        foreach ($keys as $i => $key) {
            $num    = $i + 1;
            $marker = ($key === $default) ? "\033[1;32m▶\033[0m" : ' ';
            echo "  $marker \033[32m[$num]\033[0m {$options[$key]}" . PHP_EOL;
        }

        echo "\033[33mChoose [1–" . count($keys) . "]\033[0m (default: $defaultNum): ";
        $input = trim(fgets(STDIN) ?: '');
        if ($input === '') {
            return $default;
        }
        return $keys[((int) $input) - 1] ?? $default;
    }

    private function confirmInteractive(string $question, bool $default): bool
    {
        $restore = $this->enterRawMode();
        if ($restore === null) {
            return $this->confirmFallback($question, $default);
        }

        $hint = $default ? "\033[1;32mY\033[0m/n" : "y/\033[1;32mN\033[0m";
        echo "\033[1;33m? \033[0m\033[1m$question\033[0m [$hint] ";

        while (true) {
            $key = $this->readKey();
            if ($key === 'y' || $key === 'Y') { $restore(); echo "yes" . PHP_EOL; return true; }
            if ($key === 'n' || $key === 'N') { $restore(); echo "no"  . PHP_EOL; return false; }
            if ($key === 'ENTER')             { $restore(); echo ($default ? 'yes' : 'no') . PHP_EOL; return $default; }
            if ($key === 'CANCEL')            { $restore(); echo PHP_EOL . "\033[31mCancelled.\033[0m" . PHP_EOL; exit(130); }
        }
    }

    private function confirmFallback(string $question, bool $default): bool
    {
        $hint = $default ? '[Y/n]' : '[y/N]';
        echo "\033[33m$question $hint\033[0m ";
        $answer = strtolower(trim(fgets(STDIN) ?: ''));
        return $answer === '' ? $default : in_array($answer, ['y', 'yes'], true);
    }

    /**
     * Drop the TTY into raw mode (no canonical, no echo, no signals so we
     * can intercept Ctrl+C) and return a closure that restores the prior
     * state. Returns null if stty is unavailable or the prior state can't
     * be captured — caller should then fall back to the text prompt.
     */
    private function enterRawMode(): ?\Closure
    {
        $oldStty = trim((string) shell_exec('stty -g 2>/dev/null'));
        if ($oldStty === '') {
            return null;
        }
        shell_exec('stty -icanon -echo -isig');

        $restored = false;
        $restore  = function () use ($oldStty, &$restored): void {
            if ($restored) return;
            shell_exec('stty ' . escapeshellarg($oldStty));
            $restored = true;
        };
        // Make sure the terminal is restored even if the process exits early
        // (uncaught exception, exit(), etc.) — the user shouldn't be left in
        // a broken raw-mode shell.
        register_shutdown_function($restore);
        return $restore;
    }

    /**
     * Read one logical keypress from STDIN (raw mode). Returns a symbolic
     * name for navigation keys (UP/DOWN/HOME/END/ENTER/CANCEL) or the raw
     * character for everything else.
     */
    private function readKey(): string
    {
        $first = fread(STDIN, 1);
        if ($first === false || $first === '') {
            return '';
        }

        if ($first === "\033") {
            // Arrow keys send ESC [ A/B/C/D. Wait briefly for the rest;
            // if nothing follows, treat as a bare Esc → cancel.
            $r = [STDIN]; $w = $e = null;
            if (@stream_select($r, $w, $e, 0, 50_000) > 0) {
                $rest = (string) fread(STDIN, 4);
                return match (true) {
                    str_starts_with($rest, '[A') => 'UP',
                    str_starts_with($rest, '[B') => 'DOWN',
                    str_starts_with($rest, '[C') => 'RIGHT',
                    str_starts_with($rest, '[D') => 'LEFT',
                    str_starts_with($rest, '[H') => 'HOME',
                    str_starts_with($rest, '[F') => 'END',
                    default                      => 'CANCEL',
                };
            }
            return 'CANCEL';
        }

        return match ($first) {
            "\n", "\r" => 'ENTER',
            "\x03"     => 'CANCEL',  // Ctrl+C (we disabled -isig so it lands here)
            'k', 'K'   => 'UP',      // vim-style binds
            'j', 'J'   => 'DOWN',
            default    => $first,
        };
    }

    public function stepHeader(int $step, int $total, string $title): void
    {
        $bar = str_repeat('━', 48);
        echo PHP_EOL . "\033[36m$bar\033[0m" . PHP_EOL;
        echo "\033[1;36m Step $step/$total — $title\033[0m" . PHP_EOL;
        echo "\033[36m$bar\033[0m" . PHP_EOL;
    }

    public function table(array $headers, array $rows): void
    {
        $widths = array_map('strlen', $headers);

        foreach ($rows as $row) {
            foreach (array_values($row) as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, strlen((string) $cell));
            }
        }

        $sep = '+' . implode('+', array_map(
            static fn(int $w): string => str_repeat('-', $w + 2),
            $widths
        )) . '+';

        echo $sep . PHP_EOL;
        echo '| ' . implode(' | ', array_map(
            static fn(string $h, int $w): string => str_pad($h, $w),
            $headers, $widths
        )) . ' |' . PHP_EOL;
        echo $sep . PHP_EOL;

        foreach ($rows as $row) {
            $cells = array_values($row);
            echo '| ' . implode(' | ', array_map(
                static fn(mixed $c, int $i): string => str_pad((string) $c, $widths[$i]),
                $cells, array_keys($widths)
            )) . ' |' . PHP_EOL;
        }

        echo $sep . PHP_EOL;
    }
}
