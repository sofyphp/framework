<?php

declare(strict_types=1);

use Sofy\View\UI;

$phpVer = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
$bootMs = isset($_SERVER['REQUEST_TIME_FLOAT'])
    ? round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 1) . 'ms'
    : '—';
$memMb  = round(memory_get_usage() / 1024 / 1024, 1) . 'MB';

echo UI::page(__('ui.welcome.title'))
    ->nav('Sofy', [
        '/ui-demo' => __('ui.nav.ui'),
        '/docs'    => __('ui.nav.docs'),
    ])
    ->moon(true)
    ->themeToggle()
    ->localeSwitcher()
    ->css('
        .sofy-main{max-width:960px}
        .sofy-welcome-hero{text-align:center;padding:64px 0 40px}
        .sofy-welcome-logo{font-size:clamp(64px,12vw,108px);font-weight:800;color:var(--bright);letter-spacing:-.05em;line-height:1;margin-bottom:14px}
        .sofy-welcome-logo span{color:var(--accent)}
        .sofy-welcome-sub{font-size:15px;color:var(--muted);line-height:1.9;max-width:460px;margin:0 auto 28px}
        .sofy-card-body .sofy-code{margin:0}
    ')
    ->add(
        UI::raw(
            '<div class="sofy-welcome-hero">'
            . '<div class="sofy-welcome-logo">So<span>fy</span></div>'
            . '<p class="sofy-welcome-sub">' . htmlspecialchars(__('ui.welcome.tagline'), ENT_QUOTES, 'UTF-8') . '<br>'
            . htmlspecialchars(__('ui.welcome.tagline2'), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<div class="sofy-btn-group" style="justify-content:center">'
            . UI::button(__('ui.welcome.btn_ui'),   '/ui-demo', 'primary', 'lg')
            . UI::button(__('ui.welcome.btn_docs'), '/docs',    'ghost',   'lg')
            . UI::button('GitHub', 'https://github.com/sofyphp/framework', 'ghost', 'lg')
                ->hx('target', '_blank')->hx('rel', 'noopener noreferrer')
            . '</div>'
            . '</div>'
        ),

        UI::grid(3, [
            UI::stat('PHP',    $phpVer, null, __('ui.welcome.stat_version')),
            UI::stat('Boot',   $bootMs, null, __('ui.welcome.stat_time')),
            UI::stat('Memory', $memMb,  null, __('ui.welcome.stat_memory')),
        ]),

        UI::grid(3, [
            UI::card(__('ui.welcome.feat.zero_deps'),
                UI::text(__('ui.welcome.feat.zero_deps_desc'), true)),
            UI::card(__('ui.welcome.feat.ui_comps'),
                UI::text(__('ui.welcome.feat.ui_comps_desc'), true)),
            UI::card(__('ui.welcome.feat.routing'),
                UI::text(__('ui.welcome.feat.routing_desc'), true)),
            UI::card(__('ui.welcome.feat.orm'),
                UI::text(__('ui.welcome.feat.orm_desc'), true)),
            UI::card(__('ui.welcome.feat.auth'),
                UI::text(__('ui.welcome.feat.auth_desc'), true)),
            UI::card(__('ui.welcome.feat.modules'),
                UI::text(__('ui.welcome.feat.modules_desc'), true)),
        ]),

        UI::card(__('ui.welcome.quick_start'),
            UI::code(<<<'PHP'
// routes/web.php
$router->get('/hello/{name}', function (Request $req, string $name): Response {
    return UI::page("Hello, $name!")
        ->nav('MyApp', ['/' => 'Home'])
        ->add(
            UI::hero("Hello, $name!", 'Welcome to Sofy.')
                ->action(UI::button('Get started', '/docs', 'primary')),
            UI::grid(3, [
                UI::stat('Users', 1240, '+5%'),
                UI::stat('Active',  983, '+2%'),
                UI::stat('New',      42, '+12%'),
            ]),
        )
        ->response();
});
PHP, 'php')
        ),
    )
    ->render();
