<?php

declare(strict_types=1);

namespace Main\Controllers;

use Sofy\Http\Request;
use Sofy\Http\Response;
use Sofy\Support\Lang;
use Sofy\Support\Markdown;
use Sofy\View\UI;

class DocsController extends Controller
{
    private const DOCS_PATH = __DIR__ . '/../../docs';

    private function docsPath(): string
    {
        $locale  = Lang::getLocale();
        $locPath = self::DOCS_PATH . '/' . $locale;
        return is_dir($locPath) ? $locPath : self::DOCS_PATH;
    }

    public function index(Request $request): Response
    {
        return $this->renderDoc($request, null);
    }

    public function show(Request $request, string $section): Response
    {
        return $this->renderDoc($request, $section);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function renderDoc(Request $request, ?string $section): Response
    {
        $files = $this->docFiles();

        if (empty($files)) {
            return UI::page(__('ui.docs.title'))
                ->nav('Sofy', $this->navLinks())
                ->themeToggle()
                ->localeSwitcher()
                ->add(UI::alert(__('ui.docs.no_docs'), 'warning'))
                ->response();
        }

        // Select active file
        $active = null;
        if ($section !== null) {
            foreach ($files as $file) {
                if ($file['slug'] === $section) {
                    $active = $file;
                    break;
                }
            }
        }
        $active ??= $files[0];

        $md      = (string) file_get_contents($active['path']);
        $content = Markdown::toHtml($md);
        $title   = $active['title'] ?: 'Documentation';

        // Sidebar
        $currentSlug = $active['slug'];
        $navItems    = implode('', array_map(static function (array $f) use ($currentSlug): string {
            $cls = $f['slug'] === $currentSlug ? ' active' : '';
            return '<a href="/docs/' . htmlspecialchars($f['slug'], ENT_QUOTES, 'UTF-8') . '"'
                . ' class="sofy-docs-nav-item' . $cls . '">'
                . htmlspecialchars($f['title'], ENT_QUOTES, 'UTF-8')
                . '</a>';
        }, $files));

        $sidebar = UI::raw('<nav class="sofy-docs-nav">' . $navItems . '</nav>');

        $prevNext = $this->prevNext($files, $currentSlug);

        return UI::page($title . ' — ' . __('ui.docs.title'))
            ->nav('Sofy', $this->navLinks())
            ->themeToggle()
            ->localeSwitcher()
            ->css($this->docsCss())
            ->add(
                UI::sidebarLayout(
                    sidebar:  $sidebar,
                    content:  UI::raw('<div class="sofy-docs-body">' . $content . '</div>' . $prevNext),
                    width:    '220px',
                    position: 'left',
                    gap:      '40px',
                ),
            )
            ->response();
    }

    /** @return array<array{slug:string, title:string, path:string}> */
    private function docFiles(): array
    {
        $glob = glob($this->docsPath() . '/[0-9]*.md') ?: [];
        sort($glob);

        return array_map(static function (string $path): array {
            $slug    = basename($path, '.md');
            $content = (string) file_get_contents($path);
            $title   = Markdown::title($content);
            // prettify: "01-getting-started" → "Getting started"
            if ($title === '') {
                $title = ucfirst(str_replace('-', ' ', preg_replace('/^\d+-/', '', $slug) ?? $slug));
            }
            return compact('slug', 'title', 'path');
        }, $glob);
    }

    private function prevNext(array $files, string $current): string
    {
        $idx  = array_search($current, array_column($files, 'slug'), true);
        $prev = $idx > 0 ? $files[$idx - 1] : null;
        $next = isset($files[$idx + 1]) ? $files[$idx + 1] : null;

        if ($prev === null && $next === null) {
            return '';
        }

        $prevHtml = $prev
            ? '<a href="/docs/' . htmlspecialchars($prev['slug'], ENT_QUOTES, 'UTF-8') . '" class="sofy-docs-pn sofy-docs-pn-prev">'
              . '<span class="sofy-docs-pn-dir">' . htmlspecialchars(__('ui.docs.prev'), ENT_QUOTES, 'UTF-8') . '</span>'
              . '<span class="sofy-docs-pn-title">' . htmlspecialchars($prev['title'], ENT_QUOTES, 'UTF-8') . '</span>'
              . '</a>'
            : '<span></span>';

        $nextHtml = $next
            ? '<a href="/docs/' . htmlspecialchars($next['slug'], ENT_QUOTES, 'UTF-8') . '" class="sofy-docs-pn sofy-docs-pn-next">'
              . '<span class="sofy-docs-pn-dir">' . htmlspecialchars(__('ui.docs.next'), ENT_QUOTES, 'UTF-8') . '</span>'
              . '<span class="sofy-docs-pn-title">' . htmlspecialchars($next['title'], ENT_QUOTES, 'UTF-8') . '</span>'
              . '</a>'
            : '<span></span>';

        return '<div class="sofy-docs-pagination">' . $prevHtml . $nextHtml . '</div>';
    }

    private function navLinks(): array
    {
        return ['/' => __('ui.nav.home'), '/ui-demo' => __('ui.nav.ui'), '/docs' => __('ui.nav.docs')];
    }

    private function docsCss(): string
    {
        return '
.sofy-main{max-width:1100px}
.sofy-docs-nav{display:flex;flex-direction:column;gap:2px;position:sticky;top:80px}
.sofy-docs-nav-item{display:block;padding:7px 12px;border-radius:6px;font-size:12px;color:var(--muted);text-decoration:none;transition:color var(--t),background var(--t),border-color var(--t);border-left:2px solid transparent;line-height:1.4}
.sofy-docs-nav-item:hover{color:var(--text);background:rgba(255,255,255,.03)}
.sofy-docs-nav-item.active{color:var(--accent);background:rgba(124,155,191,.07);border-left-color:var(--accent)}
.sofy-docs-body{min-width:0}
.sofy-docs-body .sofy-h{margin-top:32px;margin-bottom:10px}
.sofy-docs-body .sofy-h1{margin-top:0;font-size:26px}
.sofy-docs-body .sofy-h2{font-size:18px}
.sofy-docs-body .sofy-h3{font-size:14px;color:var(--text)}
.sofy-docs-body .sofy-h4{font-size:13px;color:var(--muted)}
.sofy-docs-body .sofy-p{margin-bottom:14px}
.sofy-docs-body .sofy-code-wrap{margin-bottom:18px}
.sofy-docs-body .sofy-code{display:block;overflow-x:auto;tab-size:4}
.sofy-docs-body .sofy-hr{margin:28px 0}
.sofy-docs-body .sofy-ul,.sofy-docs-body .sofy-ol{margin-bottom:14px}
.sofy-docs-body .sofy-tbl-wrap{margin-bottom:18px}
.sofy-docs-a{color:var(--accent);text-decoration:none}
.sofy-docs-a:hover{text-decoration:underline}
.sofy-docs-code{background:var(--surf2);border:1px solid var(--border);border-radius:4px;padding:1px 5px;font-family:var(--font);font-size:11px;color:var(--text);white-space:nowrap}
.sofy-docs-pagination{display:flex;justify-content:space-between;gap:16px;margin-top:48px;padding-top:24px;border-top:1px solid var(--border)}
.sofy-docs-pn{display:flex;flex-direction:column;gap:4px;padding:14px 18px;border:1px solid var(--border);border-radius:var(--r);text-decoration:none;background:var(--surf);transition:border-color var(--t),background var(--t);max-width:48%}
.sofy-docs-pn:hover{border-color:var(--accent);background:rgba(124,155,191,.04)}
.sofy-docs-pn-next{text-align:right;margin-left:auto}
.sofy-docs-pn-dir{font-size:10px;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
.sofy-docs-pn-title{font-size:13px;font-weight:600;color:var(--text)}
@media(max-width:640px){.sofy-docs-pagination{flex-direction:column}.sofy-docs-pn{max-width:100%}}
.hl-kw{color:#7c9bbf}.hl-str{color:#98c379}.hl-var{color:#e06c75}.hl-num{color:#d19a66}.hl-cmt{color:#636d83;font-style:italic}.hl-lit{color:#56b6c2}.hl-tag{color:#636d83}.hl-attr{color:#c678dd}.hl-flag{color:#7c9bbf}.hl-prop{color:#7c9bbf}
[data-theme=light] .hl-kw{color:#0550ae}[data-theme=light] .hl-str{color:#116329}[data-theme=light] .hl-var{color:#953800}[data-theme=light] .hl-num{color:#6639ba}[data-theme=light] .hl-cmt{color:#57606a;font-style:italic}[data-theme=light] .hl-lit{color:#0550ae}[data-theme=light] .hl-tag{color:#57606a}[data-theme=light] .hl-attr{color:#8250df}[data-theme=light] .hl-flag{color:#0550ae}[data-theme=light] .hl-prop{color:#0550ae}
';
    }
}
