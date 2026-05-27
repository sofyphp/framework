<?php

declare(strict_types=1);

namespace Sofy\Log;

class Logger
{
    /**
     * Log lines collected during the request.
     * Flushed in one write at shutdown (or immediately for ERROR/CRITICAL).
     *
     * @var string[]
     */
    private array $buffer = [];

    public function __construct(private readonly string $path)
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Guarantee the buffer is written even when the Logger instance lives
        // until process shutdown (e.g. stored in a static property on Log).
        register_shutdown_function(fn () => $this->flush());
    }

    public function debug(string $message, array $context = []): void    { $this->write('DEBUG',    $message, $context); }
    public function info(string $message, array $context = []): void     { $this->write('INFO',     $message, $context); }
    public function warning(string $message, array $context = []): void  { $this->write('WARNING',  $message, $context); }
    public function error(string $message, array $context = []): void    { $this->write('ERROR',    $message, $context); }
    public function critical(string $message, array $context = []): void { $this->write('CRITICAL', $message, $context); }

    /**
     * Write buffered entries to disk in a single call.
     * Called automatically at shutdown; can also be called explicitly.
     */
    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }
        file_put_contents($this->path, implode('', $this->buffer), FILE_APPEND | LOCK_EX);
        $this->buffer = [];
    }

    private function write(string $level, string $message, array $context): void
    {
        $date = date('Y-m-d H:i:s');
        $ctx  = $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $this->buffer[] = "[$date] $level: $message$ctx" . PHP_EOL;

        // Flush immediately for errors (don't risk losing critical context)
        // and when the buffer grows large (memory guard).
        if ($level === 'ERROR' || $level === 'CRITICAL' || count($this->buffer) >= 50) {
            $this->flush();
        }
    }
}
