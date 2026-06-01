<?php

declare(strict_types=1);

namespace Sofy\Admin\Widgets;

use Sofy\Admin\AdminWidget;
use Sofy\Core\Application;
use Sofy\View\UI;

/**
 * Half-width key/value card summarising the live runtime: framework /
 * PHP versions, OS, memory limit, current peak, and process uptime since
 * the request started. A glance gives the same picture you'd get from
 * `php -v && uname -a`.
 */
class SystemHealthWidget extends AdminWidget
{
    public int $order = 60;
    public int $cols  = 2;

    public function render(): mixed
    {
        $peak = memory_get_peak_usage() / 1024 / 1024;

        return UI::card(
            'System health',
            UI::kv([
                'Sofy'         => Application::version(),
                'PHP'          => PHP_VERSION . ' (' . PHP_SAPI . ')',
                'OS'           => trim(php_uname('s') . ' ' . php_uname('r')),
                'Memory limit' => (string) ini_get('memory_limit'),
                'Peak usage'   => number_format($peak, 1) . ' MB',
                'Server time'  => date('Y-m-d H:i:s'),
            ]),
        );
    }
}
