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
        $hint = $default ? '[Y/n]' : '[y/N]';
        echo "\033[33m$question $hint\033[0m ";
        $answer = strtolower(trim(fgets(STDIN) ?: ''));
        return $answer === '' ? $default : in_array($answer, ['y', 'yes'], true);
    }

    /**
     * Показывает нумерованное меню и возвращает выбранный ключ.
     *
     * @param  array<string|int, string> $options  ['key' => 'Label — description', ...]
     * @return string|int  Ключ выбранного пункта
     */
    public function select(string $question, array $options, string|int|null $default = null): string|int
    {
        $keys = array_keys($options);

        if ($default === null) {
            $default = $keys[0];
        }

        $defaultNum = (array_search($default, $keys, true) ?: 0) + 1;

        echo PHP_EOL . "\033[33m$question\033[0m" . PHP_EOL;

        foreach ($keys as $i => $key) {
            $num      = $i + 1;
            $label    = $options[$key];
            $marker   = ($key === $default) ? "\033[1;32m▶\033[0m" : ' ';
            echo "  $marker \033[32m[$num]\033[0m $label" . PHP_EOL;
        }

        echo "\033[33mChoose [1–" . count($keys) . "]\033[0m (default: $defaultNum): ";
        $input = trim(fgets(STDIN) ?: '');

        if ($input === '') {
            return $default;
        }

        $idx = (int) $input - 1;
        return $keys[$idx] ?? $default;
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
