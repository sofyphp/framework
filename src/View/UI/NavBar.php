<?php

declare(strict_types=1);

namespace Sofy\View\UI;

use Sofy\Support\Lang;

class NavBar extends Component
{
    private mixed $actions         = null;
    private bool  $showThemeToggle  = false;
    private bool  $showLocaleSwitcher = false;

    /**
     * @param array<string,string> $links  ['/url' => 'Label', ...]
     */
    public function __construct(
        private readonly string $brand    = 'Sofy',
        private readonly string $brandUrl = '/',
        private readonly array  $links    = [],
    ) {}

    public function actions(mixed $actions): static
    {
        $this->actions = $actions;
        return $this;
    }

    public function withThemeToggle(): static
    {
        $this->showThemeToggle = true;
        return $this;
    }

    public function withLocaleSwitcher(): static
    {
        $this->showLocaleSwitcher = true;
        return $this;
    }

    public function render(): string
    {
        // Brand — first 2 chars plain, the rest accented (e.g. "So" + "fy")
        $name   = $this->e($this->brand);
        $brand  = '<a href="' . $this->e($this->brandUrl) . '" class="sofy-nav-brand">'
            . preg_replace('/^(.{2})(.*)$/', '$1<span>$2</span>', $name)
            . '</a>';

        // Links
        $navLinks = '';
        if (!empty($this->links)) {
            $current  = $_SERVER['REQUEST_URI'] ?? '';
            $navLinks = '<nav class="sofy-nav-links">';
            foreach ($this->links as $href => $label) {
                $active    = rtrim($href, '/') === rtrim(strtok($current, '?'), '/')
                    ? ' active' : '';
                $navLinks .= '<a href="' . $this->e($href) . '" class="sofy-nav-link' . $active . '">'
                    . $this->e($label) . '</a>';
            }
            $navLinks .= '</nav>';
        }

        $actions = $this->actions !== null
            ? '<div class="sofy-nav-actions">' . $this->slot($this->actions) . '</div>'
            : '';

        $themeBtn = $this->showThemeToggle
            ? '<button class="sofy-theme-btn" onclick="sofyToggleTheme()" title="Toggle theme" aria-label="Toggle theme">'
                . '<span class="t-icon-swap" data-state="a">'
                . '<span class="t-icon" data-icon="a">☾</span>'
                . '<span class="t-icon" data-icon="b">☀</span>'
                . '</span></button>'
            : '';

        $localeSw = '';
        if ($this->showLocaleSwitcher) {
            $current  = Lang::getLocale();
            $localeSw = '<div class="sofy-locale-sw">';
            foreach (['en', 'ru'] as $loc) {
                $cls      = $loc === $current ? ' active' : '';
                $localeSw .= '<a href="/lang/' . $loc . '" class="sofy-locale-btn' . $cls . '">' . strtoupper($loc) . '</a>';
            }
            $localeSw .= '</div>';
        }

        $hamburger = !empty($this->links)
            ? '<button class="sofy-hamburger" aria-label="Menu" aria-expanded="false"'
              . ' onclick="var n=this.closest(\'.sofy-nav\');n.classList.toggle(\'nav-open\');this.setAttribute(\'aria-expanded\',n.classList.contains(\'nav-open\'))">☰</button>'
            : '';

        return '<header class="sofy-nav">'
            . $brand
            . $navLinks
            . '<span class="sofy-nav-spacer"></span>'
            . $actions
            . $localeSw
            . $themeBtn
            . $hamburger
            . '</header>';
    }
}
