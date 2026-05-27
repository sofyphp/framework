<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

/**
 * Interactive PHP REPL with full framework context.
 *
 *   php sofy repl
 *
 * Features:
 *  - Evaluate any PHP expression or statement
 *  - Auto-return: `User::find(1)` prints the result without `echo`
 *  - Multi-line input (detected by open braces/parens)
 *  - readline history (when ext-readline is available)
 *  - Coloured output
 *  - Special commands: help, clear, exit
 */
class ReplCommand extends Command
{
    protected string $signature   = 'repl';
    protected string $description = 'Start an interactive PHP REPL with framework context';

    private const ANSI_RESET  = "\033[0m";
    private const ANSI_GREEN  = "\033[32m";
    private const ANSI_YELLOW = "\033[33m";
    private const ANSI_RED    = "\033[31m";
    private const ANSI_CYAN   = "\033[36m";
    private const ANSI_MUTED  = "\033[90m";

    public function handle(): int
    {
        $this->printBanner();

        $buffer = '';
        $historyFile = $this->historyPath();

        if (function_exists('readline_read_history') && $historyFile !== null) {
            @readline_read_history($historyFile);
        }

        while (true) {
            $prompt  = $buffer === '' ? self::ANSI_GREEN . '>>> ' . self::ANSI_RESET : self::ANSI_MUTED . '... ' . self::ANSI_RESET;
            $line    = $this->readLine($prompt);

            if ($line === false) {
                break; // Ctrl+D / EOF
            }

            $trimmed = trim($line);

            // ── Special commands ─────────────────────────────────────────────

            if ($buffer === '') {
                if ($trimmed === '' ) {
                    continue;
                }
                if (in_array($trimmed, ['exit', 'quit', 'q'], true)) {
                    $this->writeln(self::ANSI_MUTED . 'Goodbye.' . self::ANSI_RESET);
                    break;
                }
                if ($trimmed === 'help') {
                    $this->printHelp();
                    continue;
                }
                if ($trimmed === 'clear') {
                    echo "\033[2J\033[H";
                    continue;
                }
            }

            $this->addHistory($line, $historyFile);
            $buffer .= $line . "\n";

            // ── Check completeness ───────────────────────────────────────────

            if (!$this->isComplete($buffer)) {
                continue; // wait for more input
            }

            $code   = $buffer;
            $buffer = '';

            // ── Evaluate ─────────────────────────────────────────────────────

            $this->evaluate($code);
        }

        if (function_exists('readline_write_history') && $historyFile !== null) {
            @readline_write_history($historyFile);
        }

        return 0;
    }

    // ── Evaluation ────────────────────────────────────────────────────────────

    private function evaluate(string $code): void
    {
        $code = trim($code);

        ob_start();
        $result   = null;
        $hasReturn = false;
        $error    = null;

        // First: try "return <expr>;" to capture the value
        $exprCode = rtrim($code, '; ');
        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $result    = eval('return ' . $exprCode . ';');
            $hasReturn = true;
        } catch (\ParseError) {
            // Not a simple expression — try as statements
            try {
                eval($code . (str_ends_with(trim($code), ';') ? '' : ';'));
            } catch (\Throwable $e) {
                $error = $e;
            }
        } catch (\Throwable $e) {
            $error = $e;
        }

        $output = ob_get_clean() ?: '';

        if ($output !== '') {
            echo $output;
            if (!str_ends_with($output, "\n")) {
                echo "\n";
            }
        }

        if ($error !== null) {
            $label = get_class($error);
            $this->writeln(self::ANSI_RED . $label . ': ' . $error->getMessage() . self::ANSI_RESET);
            return;
        }

        if ($hasReturn && $result !== null) {
            $this->writeln(self::ANSI_CYAN . '=> ' . self::ANSI_RESET . $this->dump($result));
        }
    }

    // ── Value dumping ─────────────────────────────────────────────────────────

    private function dump(mixed $value, int $depth = 0): string
    {
        $indent = str_repeat('    ', $depth);

        if ($value === null) {
            return self::ANSI_MUTED . 'null' . self::ANSI_RESET;
        }
        if (is_bool($value)) {
            return self::ANSI_YELLOW . ($value ? 'true' : 'false') . self::ANSI_RESET;
        }
        if (is_int($value) || is_float($value)) {
            return self::ANSI_YELLOW . (string) $value . self::ANSI_RESET;
        }
        if (is_string($value)) {
            return self::ANSI_GREEN . '"' . addslashes($value) . '"' . self::ANSI_RESET;
        }
        if (is_array($value)) {
            if (empty($value)) {
                return '[]';
            }
            if ($depth >= 3) {
                return '[…' . count($value) . ' items]';
            }
            $isList = array_is_list($value);
            $lines  = [];
            foreach ($value as $k => $v) {
                $key     = $isList ? '' : (self::ANSI_MUTED . $k . self::ANSI_RESET . ' => ');
                $lines[] = $indent . '    ' . $key . $this->dump($v, $depth + 1);
            }
            return "[\n" . implode(",\n", $lines) . "\n" . $indent . ']';
        }
        if (is_object($value)) {
            $class = get_class($value);
            if ($depth >= 2) {
                return self::ANSI_MUTED . $class . '{…}' . self::ANSI_RESET;
            }
            if (method_exists($value, 'toArray')) {
                return self::ANSI_MUTED . $class . self::ANSI_RESET . ' ' . $this->dump($value->toArray(), $depth);
            }
            return self::ANSI_MUTED . $class . '{…}' . self::ANSI_RESET;
        }

        return (string) $value;
    }

    // ── Input helpers ─────────────────────────────────────────────────────────

    private function readLine(string $prompt): string|false
    {
        if (function_exists('readline')) {
            return readline($prompt);
        }
        echo $prompt;
        $line = fgets(STDIN);
        return $line === false ? false : rtrim($line, "\n");
    }

    private function addHistory(string $line, ?string $historyFile): void
    {
        if (trim($line) === '') {
            return;
        }
        if (function_exists('readline_add_history')) {
            readline_add_history($line);
        }
    }

    private function historyPath(): ?string
    {
        $home = $_SERVER['HOME'] ?? getenv('HOME') ?: null;
        return $home ? $home . '/.sofy_repl_history' : null;
    }

    // ── Syntax completeness check ─────────────────────────────────────────────

    /**
     * Returns true when the buffered input appears syntactically complete.
     * Uses PHP's tokenizer so strings/comments don't confuse brace counting.
     */
    private function isComplete(string $code): bool
    {
        $tokens = @token_get_all('<?php ' . $code);

        $open  = 0;
        $depth = 0;

        foreach ($tokens as $token) {
            $t = is_array($token) ? $token[0] : $token;
            if ($t === '{' || $t === '(') {
                $depth++;
            } elseif ($t === '}' || $t === ')') {
                $depth--;
            } elseif ($t === '[') {
                $open++;
            } elseif ($t === ']') {
                $open--;
            }
        }

        return $depth <= 0 && $open <= 0;
    }

    // ── UI ────────────────────────────────────────────────────────────────────

    private function printBanner(): void
    {
        $php = PHP_VERSION;
        $this->writeln('');
        $this->writeln(self::ANSI_CYAN . '  Lu' . self::ANSI_YELLOW . 'ne' . self::ANSI_CYAN . ' REPL' . self::ANSI_RESET
            . self::ANSI_MUTED . "  (PHP $php — type 'help' for commands, Ctrl+D to exit)" . self::ANSI_RESET);
        $this->writeln('');
    }

    private function printHelp(): void
    {
        $this->writeln(self::ANSI_CYAN . '  Commands:' . self::ANSI_RESET);
        $this->writeln('    ' . self::ANSI_YELLOW . 'exit / quit / q' . self::ANSI_RESET . '  Exit the REPL');
        $this->writeln('    ' . self::ANSI_YELLOW . 'clear'           . self::ANSI_RESET . '            Clear the screen');
        $this->writeln('    ' . self::ANSI_YELLOW . 'help'            . self::ANSI_RESET . '             Show this help');
        $this->writeln('');
        $this->writeln(self::ANSI_CYAN . '  Tips:' . self::ANSI_RESET);
        $this->writeln('    • Expressions are auto-returned:  ' . self::ANSI_GREEN . 'User::find(1)' . self::ANSI_RESET);
        $this->writeln('    • Multi-line input supported (keep braces open)');
        $this->writeln('    • Use Ctrl+D to exit, ↑↓ for history');
        $this->writeln('');
    }

    private function writeln(string $text): void
    {
        echo $text . "\n";
    }
}
