<?php

declare(strict_types=1);

namespace Sofy\Admin\Controllers;

use Sofy\Admin\Admin;
use Sofy\Http\Request;
use Sofy\Http\Response;
use Sofy\Module\Marketplace\Catalog;
use Sofy\Module\Marketplace\InstallResult;
use Sofy\Module\Marketplace\Installer;
use Sofy\View\Icons;
use Sofy\View\UI;

/**
 * Marketplace UI — browse the catalog, install / uninstall modules.
 *
 * The catalog merges entries fetched from config('marketplace.catalog_url')
 * with manifests detected on disk under modules/. The page shows one card
 * per known module with its install state — "installed" gets an
 * Uninstall button, the rest get Install.
 */
class MarketplaceController
{
    public function __construct(
        private readonly Catalog   $catalog   = new Catalog(),
        private readonly Installer $installer = new Installer(),
    ) {}

    public function index(Request $request): Response
    {
        $modules = $this->catalog->all();

        $category = trim((string) $request->input('category', ''));
        $search   = trim((string) $request->input('q', ''));

        $allCategories = [];
        foreach ($modules as $m) {
            foreach ((array) ($m['categories'] ?? []) as $c) {
                $allCategories[(string) $c] = true;
            }
        }
        ksort($allCategories);

        if ($category !== '') {
            $modules = array_values(array_filter($modules, static fn(array $m): bool
                => in_array($category, (array) ($m['categories'] ?? []), true)));
        }
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $modules = array_values(array_filter($modules, static function (array $m) use ($needle): bool {
                $haystack = mb_strtolower(implode(' ', [
                    (string) ($m['name']        ?? ''),
                    (string) ($m['slug']        ?? ''),
                    (string) ($m['description'] ?? ''),
                    implode(' ', (array) ($m['tags'] ?? [])),
                ]));
                return $needle === '' || str_contains($haystack, $needle);
            }));
        }

        $installedCount = count(array_filter($modules, static fn(array $m): bool => !empty($m['installed'])));
        $totalCount     = count($modules);

        $refreshForm = $this->csrfForm('/admin/system/marketplace/refresh', UI::raw(
            '<button class="sofy-btn sofy-btn-ghost" type="submit">'
            . UI::icon('refresh', size: 13) . ' Обновить каталог</button>',
        ));

        $catChips = '<div class="sofy-mp-chips">'
            . $this->chip('', 'Все', $category === '')
            . implode('', array_map(
                fn(string $c) => $this->chip($c, $c, $category === $c),
                array_keys($allCategories),
            ))
            . '</div>';

        $searchForm = UI::raw(
            '<form method="GET" action="/admin/system/marketplace" class="sofy-mp-search">'
            . ($category !== '' ? '<input type="hidden" name="category" value="' . htmlspecialchars($category, ENT_QUOTES, 'UTF-8') . '">' : '')
            . '<input type="search" name="q" placeholder="Поиск: имя, описание, тег" value="' . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . '" class="sofy-form-ctrl">'
            . '<button type="submit" class="sofy-btn sofy-btn-ghost">Найти</button>'
            . '</form>',
        );

        $body = empty($modules)
            ? UI::emptyState(
                'Каталог пуст',
                'Удалённый каталог недоступен или не содержит модулей под текущий фильтр. Добавьте свой — PR в sofyphp/marketplace.',
                icon: '◯',
            )
            : UI::raw($this->renderGrid($modules));

        return Admin::page('Маркетплейс')
            ->header(
                'Маркетплейс (' . $installedCount . ' / ' . $totalCount . ')',
                $refreshForm,
            )
            ->add(
                UI::alert(
                    UI::raw(
                        'Модули выполняют код в контексте этого приложения. Устанавливайте только из доверенных источников. '
                        . 'Источник каталога: <code class="sofy-docs-code">' . htmlspecialchars($this->catalogUrlForDisplay(), ENT_QUOTES, 'UTF-8') . '</code>',
                    ),
                    'info',
                    'Внимание',
                ),
                UI::raw($catChips),
                $searchForm,
                $body,
                UI::raw($this->styles()),
            )
            ->response();
    }

    public function install(Request $request, string $slug): Response
    {
        $result = $this->installer->install($slug);
        $this->catalog->refresh();
        return $this->renderResult($slug, 'install', $result);
    }

    public function uninstall(Request $request, string $slug): Response
    {
        $result = $this->installer->uninstall($slug);
        $this->catalog->refresh();
        return $this->renderResult($slug, 'uninstall', $result);
    }

    public function refresh(): Response
    {
        $this->catalog->refresh();
        return Response::redirect('/admin/system/marketplace');
    }

    // ── Rendering helpers ────────────────────────────────────────────────────

    private function renderGrid(array $modules): string
    {
        $cards = '';
        foreach ($modules as $m) {
            $cards .= $this->renderCard($m);
        }
        return '<div class="sofy-mp-grid">' . $cards . '</div>';
    }

    private function renderCard(array $m): string
    {
        $slug      = (string) ($m['slug'] ?? '');
        $name      = (string) ($m['name'] ?? $slug);
        $author    = (string) ($m['author'] ?? '—');
        $version   = (string) ($m['version'] ?? '?');
        $desc      = (string) ($m['description'] ?? '');
        $homepage  = (string) ($m['homepage'] ?? '');
        $installed = !empty($m['installed']);
        $cats      = array_map('strval', (array) ($m['categories'] ?? []));

        $catBadges = '';
        foreach ($cats as $c) {
            $catBadges .= '<span class="sofy-mp-cat">' . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . '</span>';
        }

        $homeLink = $homepage !== ''
            ? '<a class="sofy-docs-a" target="_blank" rel="noopener" href="' . htmlspecialchars($homepage, ENT_QUOTES, 'UTF-8') . '">репозиторий →</a>'
            : '';

        // Action — install / uninstall, both POST with CSRF.
        if ($installed) {
            $action = $this->csrfForm(
                '/admin/system/marketplace/' . rawurlencode($slug) . '/uninstall',
                UI::raw(
                    '<button class="sofy-btn sofy-btn-ghost" type="submit" '
                    . 'onclick="return confirm(\'Удалить модуль ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '? Папка modules/' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '/ будет удалена.\');">'
                    . UI::icon('trash', size: 13) . ' Удалить</button>',
                ),
            );
            $stateBadge = '<span class="sofy-mp-state sofy-mp-state-on">'
                . UI::icon('check-circle', size: 12) . ' Установлен</span>';
        } else {
            $action = $this->csrfForm(
                '/admin/system/marketplace/' . rawurlencode($slug) . '/install',
                UI::raw(
                    '<button class="sofy-btn sofy-btn-primary" type="submit" '
                    . 'onclick="return confirm(\'Установить ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '? Код модуля будет скачан и распакован в modules/.\');">'
                    . UI::icon('download', size: 13) . ' Установить</button>',
                ),
            );
            $stateBadge = '<span class="sofy-mp-state">Не установлен</span>';
        }

        return '<article class="sofy-mp-card' . ($installed ? ' sofy-mp-card-on' : '') . '">'
            . '<header class="sofy-mp-card-head">'
            . '<h3>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</h3>'
            . $stateBadge
            . '</header>'
            . '<p class="sofy-mp-desc">' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<div class="sofy-mp-meta">'
            . '<span><strong>v' . htmlspecialchars($version, ENT_QUOTES, 'UTF-8') . '</strong></span>'
            . '<span class="sofy-mp-muted">' . htmlspecialchars($author, ENT_QUOTES, 'UTF-8') . '</span>'
            . '</div>'
            . ($catBadges !== '' ? '<div class="sofy-mp-cats">' . $catBadges . '</div>' : '')
            . '<footer class="sofy-mp-card-foot">'
            . (string) $action
            . '<span class="sofy-mp-muted">' . $homeLink . '</span>'
            . '</footer>'
            . '</article>';
    }

    private function renderResult(string $slug, string $kind, InstallResult $result): Response
    {
        $title = ($kind === 'install' ? 'Установка' : 'Удаление') . ' — ' . ($result->ok ? 'готово' : 'ошибка');
        $alert = UI::alert(
            UI::raw(nl2br(htmlspecialchars($result->message, ENT_QUOTES, 'UTF-8'))),
            $result->ok ? 'success' : 'danger',
            $title,
        );

        $logBody = empty($result->log)
            ? UI::raw('<em class="sofy-muted">Лог пуст.</em>')
            : UI::raw(
                '<pre class="sofy-update-log">'
                . htmlspecialchars(implode("\n", $result->log), ENT_QUOTES, 'UTF-8')
                . '</pre>',
            );

        return Admin::page($title)
            ->header($title, UI::button('← Каталог', '/admin/system/marketplace', 'ghost'))
            ->add(
                $alert,
                UI::card('Лог операции', $logBody),
                UI::raw($this->styles()),
            )
            ->response();
    }

    private function csrfForm(string $action, mixed $inner): \Sofy\View\UI\Component
    {
        $csrf = function_exists('csrf_token') ? csrf_token() : '';
        return UI::raw(
            '<form method="POST" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '" style="display:inline">'
            . ($csrf !== '' ? '<input type="hidden" name="_token" value="' . htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') . '">' : '')
            . (is_object($inner) ? (string) $inner : (string) $inner)
            . '</form>',
        );
    }

    private function chip(string $value, string $label, bool $active): string
    {
        $href = '/admin/system/marketplace' . ($value !== '' ? '?category=' . rawurlencode($value) : '');
        $cls  = 'sofy-mp-chip' . ($active ? ' sofy-mp-chip-active' : '');
        return '<a class="' . $cls . '" href="' . $href . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    private function catalogUrlForDisplay(): string
    {
        $url = (string) (function_exists('config') ? config('marketplace.catalog_url', '') : '');
        return $url !== '' ? $url : 'docs/marketplace.json (bundled)';
    }

    private function styles(): string
    {
        return <<<CSS
        <style>
            .sofy-mp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;margin:8px 0}
            .sofy-mp-card{display:flex;flex-direction:column;gap:8px;padding:16px;border:1px solid var(--border);border-radius:12px;background:var(--surface)}
            .sofy-mp-card-on{border-color:var(--accent);box-shadow:0 0 0 1px var(--accent) inset}
            .sofy-mp-card-head{display:flex;align-items:center;justify-content:space-between;gap:8px}
            .sofy-mp-card-head h3{margin:0;font-size:15px}
            .sofy-mp-state{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;font-size:11px;border-radius:999px;background:rgba(148,163,184,.18);color:#475569;font-weight:600}
            .sofy-mp-state-on{background:rgba(34,197,94,.12);color:#15803d}
            .sofy-mp-desc{margin:0;font-size:13px;line-height:1.45;color:var(--text);min-height:36px}
            .sofy-mp-meta{display:flex;justify-content:space-between;gap:8px;font-size:12px}
            .sofy-mp-muted{color:var(--muted)}
            .sofy-mp-cats{display:flex;flex-wrap:wrap;gap:4px}
            .sofy-mp-cat{display:inline-flex;padding:1px 8px;font-size:11px;border-radius:999px;background:var(--surface-2,#f5f1ea);color:var(--muted)}
            .sofy-mp-card-foot{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:auto;padding-top:6px;border-top:1px dashed var(--border)}
            .sofy-mp-chips{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 12px}
            .sofy-mp-chip{display:inline-flex;align-items:center;padding:4px 12px;border-radius:999px;border:1px solid var(--border);background:var(--surface);color:var(--text);font-size:12px;font-weight:600;text-decoration:none}
            .sofy-mp-chip:hover{border-color:var(--accent)}
            .sofy-mp-chip-active{border-color:var(--accent);background:var(--accent-soft,rgba(255,107,90,.12));color:var(--accent)}
            .sofy-mp-search{display:flex;gap:8px;align-items:center;margin:0 0 12px;max-width:520px}
            .sofy-mp-search .sofy-form-ctrl{flex:1}
            .sofy-update-log{background:#1a1a1a;color:#e8e8e8;border-radius:8px;padding:14px;font-family:var(--mono);font-size:12.5px;max-height:480px;overflow:auto;white-space:pre-wrap}
        </style>
        CSS;
    }
}
