<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Breadcrumb extends Component
{
    /**
     * Pass items as ['Label' => '/url', 'Current' => null]
     * A null URL renders the item as plain text (current page).
     *
     * @param array<string, string|null> $items
     */
    public function __construct(private readonly array $items) {}

    public function render(): string
    {
        $html = '<nav aria-label="breadcrumb"><ol class="sofy-bc">';
        foreach ($this->items as $label => $url) {
            if ($url === null) {
                $html .= '<li>' . $this->e($label) . '</li>';
            } else {
                $html .= '<li><a href="' . $this->e($url) . '">' . $this->e($label) . '</a></li>';
            }
        }
        return $html . '</ol></nav>';
    }
}
