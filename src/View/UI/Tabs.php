<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Tabs extends Component
{
    private static int $instances = 0;

    private readonly string $id;

    /**
     * @param array<string, mixed> $tabs   ['Tab Label' => Component|string, ...]
     */
    public function __construct(
        private readonly array $tabs,
        private readonly int   $default = 0,
    ) {
        self::$instances++;
        $this->id = 'sofy-tabs-' . self::$instances;
    }

    public function render(): string
    {
        $keys  = array_keys($this->tabs);
        $btns  = '';
        $panels = '';

        foreach ($keys as $i => $label) {
            $panelId = $this->id . '-' . $i;
            $active  = $i === $this->default ? ' active' : '';

            $btns .= '<button class="sofy-tab-btn' . $active . '" data-tab="' . $panelId . '">'
                . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8')
                . '</button>';

            $panels .= '<div id="' . $panelId . '" class="sofy-tab-panel' . $active . '">'
                . $this->slot($this->tabs[$label])
                . '</div>';
        }

        return '<div class="sofy-tabs">'
            . '<div class="sofy-tab-list">' . $btns . '</div>'
            . $panels
            . '</div>';
    }
}
