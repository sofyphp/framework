<?php

declare(strict_types=1);

namespace Sofy\Admin\Widgets;

use Sofy\Admin\AdminWidget;
use Sofy\Core\Application;
use Sofy\View\UI;

/**
 * Stat tile: how many modules the framework auto-discovered under modules/.
 * The trend slot shows the first module name as a small clue toward what
 * is actually loaded.
 */
class ModulesCountWidget extends AdminWidget
{
    public int $order = 30;
    public int $cols  = 1;

    public function render(): mixed
    {
        try {
            $modules = Application::getInstance()->getModuleLoader()->modules();
        } catch (\Throwable) {
            $modules = [];
        }

        $first = $modules[0] ?? null;
        $hint  = is_object($first) ? (new \ReflectionClass($first))->getShortName() : null;

        return UI::stat(
            'Modules',
            number_format(count($modules)),
            trend: $hint,
            description: 'auto-discovered',
        );
    }
}
