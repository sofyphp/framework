<?php

declare(strict_types=1);

namespace Sofy\Admin\Widgets;

use Sofy\Admin\AdminWidget;
use Sofy\Core\Application;
use Sofy\View\UI;

/**
 * Full-width hero greeting at the top of /admin. Pulls the framework
 * version straight from composer.json so the welcome line stays in sync
 * with the actual deployed build, not a hard-coded string.
 */
class WelcomeWidget extends AdminWidget
{
    public int $order = 0;   // first
    public int $cols  = 4;   // full row

    public function render(): mixed
    {
        $version = Application::version();
        $when    = date('l, F jS — H:i');

        return UI::hero(
            'Welcome to Sofy Admin',
            "Sofy v{$version} · {$when}",
        );
    }
}
