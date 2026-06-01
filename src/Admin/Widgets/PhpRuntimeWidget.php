<?php

declare(strict_types=1);

namespace Sofy\Admin\Widgets;

use Sofy\Admin\AdminWidget;
use Sofy\View\UI;

/**
 * Stat tile: PHP version with the live SAPI as a trend chip, and peak
 * memory usage so far in the request as the description.
 */
class PhpRuntimeWidget extends AdminWidget
{
    public int $order = 40;
    public int $cols  = 1;

    public function render(): mixed
    {
        $peak = memory_get_peak_usage() / 1024 / 1024;

        return UI::stat(
            'PHP',
            PHP_VERSION,
            trend: PHP_SAPI,
            description: number_format($peak, 1) . ' MB peak',
        );
    }
}
