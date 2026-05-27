<?php

declare(strict_types=1);

namespace Sofy\View\UI;

/**
 * Development debug bar — fixed bottom toolbar.
 *
 * Shows: execution time, peak memory, PHP version, request method/path, env.
 * Only render in APP_DEBUG=true contexts.
 *
 * Usage:
 *   UI::page('Title')->add(..., UI::debugBar())->render()
 */
class DebugBar extends Component
{
    public function render(): string
    {
        $time   = defined('SOFY_START')
            ? round((microtime(true) - constant('SOFY_START')) * 1000, 1) . ' ms'
            : '? ms';
        $memory = round(memory_get_peak_usage(true) / 1024 / 1024, 1) . ' MB';
        $php    = 'PHP ' . PHP_VERSION;
        $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $path   = $_SERVER['REQUEST_URI']    ?? '/';
        $env    = $_ENV['APP_ENV'] ?? (string) getenv('APP_ENV') ?: 'production';

        $envCls = match ($env) {
            'local', 'development' => 'sofy-dbg-success',
            'testing'              => 'sofy-dbg-warning',
            default                => 'sofy-dbg-danger',
        };

        $methodLower = strtolower($method);
        $pathShort   = htmlspecialchars(substr($path, 0, 64), ENT_QUOTES, 'UTF-8');

        return '<div id="sofy-debugbar" class="sofy-dbg">'
            . '<div class="sofy-dbg-inner">'
            . $this->pill('Sofy', 'sofy-dbg-brand')
            . $this->pill($env, $envCls)
            . $this->pill('⏱ ' . $time, 'sofy-dbg-info')
            . $this->pill('⬙ ' . $memory, 'sofy-dbg-info')
            . $this->pill($php, 'sofy-dbg-info')
            . '<div class="sofy-dbg-spacer"></div>'
            . '<span class="sofy-dbg-req">'
            . '<span class="sofy-dbg-method sofy-dbg-method-' . $methodLower . '">' . htmlspecialchars($method, ENT_QUOTES, 'UTF-8') . '</span>'
            . '<span class="sofy-dbg-path">' . $pathShort . '</span>'
            . '</span>'
            . '<button class="sofy-dbg-close" onclick="this.closest(\'#sofy-debugbar\').remove()" title="Close">✕</button>'
            . '</div>'
            . '</div>';
    }

    private function pill(string $text, string $cls): string
    {
        return '<span class="sofy-dbg-pill ' . $cls . '">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}
