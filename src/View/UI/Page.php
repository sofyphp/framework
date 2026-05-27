<?php

declare(strict_types=1);

namespace Sofy\View\UI;

use Sofy\Http\Response;
use Sofy\Support\Lang;

class Page extends Component
{
    private array   $components   = [];
    private ?NavBar $navbar       = null;
    private ?string $pageTitle    = null;
    private mixed   $pageActions  = null;
    private bool    $showMoon     = true;
    private bool    $showFooter   = true;
    private string  $extraCss     = '';
    private bool    $themeToggle  = false;
    private bool    $withHtmx     = false;

    public function __construct(private readonly string $title) {}

    // ── Builder API ───────────────────────────────────────────────────────────

    /** Add a navigation bar. */
    public function nav(string $brand, array $links = [], mixed $actions = null): static
    {
        $this->navbar = (new NavBar($brand, '/', $links))
            ->actions($actions);
        return $this;
    }

    /** Page-level title + optional action buttons shown above content. */
    public function header(string $title, mixed $actions = null): static
    {
        $this->pageTitle   = $title;
        $this->pageActions = $actions;
        return $this;
    }

    public function add(mixed ...$components): static
    {
        array_push($this->components, ...$components);
        return $this;
    }

    public function moon(bool $show): static
    {
        $this->showMoon = $show;
        return $this;
    }

    public function footer(bool $show): static
    {
        $this->showFooter = $show;
        return $this;
    }

    public function css(string $css): static
    {
        $this->extraCss = $css;
        return $this;
    }

    /** Enable dark/light theme toggle button in the nav bar. */
    public function themeToggle(): static
    {
        $this->themeToggle = true;
        return $this;
    }

    /** Enable EN/RU locale switcher in the nav bar. */
    public function localeSwitcher(): static
    {
        if ($this->navbar !== null) {
            $this->navbar->withLocaleSwitcher();
        }
        return $this;
    }

    /** Load HTMX from CDN — enables hx-* attributes on all components. */
    public function withHtmx(): static
    {
        $this->withHtmx = true;
        return $this;
    }

    // ── Output ────────────────────────────────────────────────────────────────

    public function response(int $status = 200): Response
    {
        return new Response($this->render(), $status);
    }

    public function render(): string
    {
        if ($this->themeToggle && $this->navbar !== null) {
            $this->navbar->withThemeToggle();
        }

        $themeScript = $this->themeToggle
            ? '<script>(function(){var t=localStorage.getItem("sofy-theme");if(!t){t=window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";}document.documentElement.setAttribute("data-theme",t);})();</script>'
            : '';

        $htmxScript = $this->withHtmx
            ? '<script src="https://unpkg.com/htmx.org@2/dist/htmx.min.js" defer></script>'
            : '';

        $moon = $this->showMoon ? '<div class="sofy-moon"></div>' : '';
        $nav  = $this->navbar ? $this->navbar->render() : '';

        $pageHeader = '';
        if ($this->pageTitle !== null) {
            $actions    = $this->pageActions !== null
                ? '<div class="sofy-page-actions">' . $this->slot($this->pageActions) . '</div>'
                : '';
            $pageHeader = '<div class="sofy-page-hdr">'
                . '<h1 class="sofy-page-title">' . htmlspecialchars($this->pageTitle, ENT_QUOTES, 'UTF-8') . '</h1>'
                . $actions
                . '</div>';
        }

        $body = implode('', array_map(fn($c) => (string) $c, $this->components));

        $footer = $this->showFooter
            ? '<footer class="sofy-footer">So<span>fy</span> Framework</footer>'
            : '';

        $extra = $this->extraCss !== ''
            ? '<style>' . $this->extraCss . '</style>'
            : '';

        return '<!DOCTYPE html>'
            . '<html lang="' . htmlspecialchars(Lang::getLocale(), ENT_QUOTES, 'UTF-8') . '">'
            . '<head>'
            . $themeScript
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . htmlspecialchars($this->title, ENT_QUOTES, 'UTF-8') . '</title>'
            . '<style>' . $this->getCss() . '</style>'
            . $extra
            . $htmxScript
            . '</head>'
            . '<body>'
            . $moon
            . '<div class="sofy-wrap">'
            . $nav
            . '<main class="sofy-main">'
            . $pageHeader
            . $body
            . '</main>'
            . $footer
            . '</div>'
            . $this->getJs()
            . '</body>'
            . '</html>';
    }

    // ── CSS ───────────────────────────────────────────────────────────────────

    private function getCss(): string
    {
        return <<<'CSS'
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --bg:#faf6f0;--surf:#ffffff;--surf2:#f5efe7;
    --border:#efe6da;--text:#574d44;--muted:#a89a8b;--bright:#33271d;
    --accent:#d97757;--accent2:#b08fd0;
    --success:#5ba373;--warning:#cf9440;--danger:#d96a52;--info:#5b93c4;
    --font:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;
    --mono:'SF Mono','Fira Code','Cascadia Code',ui-monospace,monospace;
    --r:16px;--t:.18s ease;
    --shadow:0 4px 18px rgba(120,85,55,.06),0 1px 3px rgba(120,85,55,.05);
    --shadow-lg:0 14px 44px rgba(120,85,55,.12)
}
html,body{min-height:100%;background:var(--bg);color:var(--text);font-family:var(--font);font-size:14px;line-height:1.7}
html{scrollbar-width:thin;scrollbar-color:var(--border) transparent}
html::-webkit-scrollbar{width:6px;height:6px}
html::-webkit-scrollbar-track{background:transparent}
html::-webkit-scrollbar-thumb{background:var(--border);border-radius:100px}
html::-webkit-scrollbar-thumb:hover{background:var(--accent2)}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background:
    radial-gradient(60vw 60vw at 6% -8%,rgba(217,119,87,.10) 0%,transparent 60%),
    radial-gradient(55vw 55vw at 99% 6%,rgba(176,143,208,.12) 0%,transparent 60%),
    radial-gradient(55vw 55vw at 50% 112%,rgba(245,201,150,.11) 0%,transparent 62%)}

/* soft glow orb (was the moon) */
.sofy-moon{position:fixed;top:-110px;right:-80px;width:320px;height:320px;border-radius:50%;
    background:radial-gradient(circle at 38% 36%,rgba(255,226,198,.95) 0%,rgba(233,160,120,.55) 42%,rgba(217,119,87,.22) 68%,transparent 100%);
    filter:blur(6px);opacity:.6;pointer-events:none;z-index:0}

/* layout */
.sofy-wrap{position:relative;z-index:1}
.sofy-main{max-width:960px;margin:0 auto;padding:36px 24px}

/* nav */
.sofy-nav{display:flex;align-items:center;gap:20px;padding:13px 24px;border-bottom:1px solid var(--border);position:sticky;top:0;background:rgba(255,250,245,.8);backdrop-filter:blur(10px) saturate(1.4);z-index:10}
.sofy-nav-brand{font-weight:700;font-size:14px;color:var(--bright);text-decoration:none}
.sofy-nav-brand span{color:var(--accent)}
.sofy-nav-links{display:flex;gap:2px}
.sofy-nav-link{font-size:11px;color:var(--muted);text-decoration:none;letter-spacing:.06em;text-transform:uppercase;padding:5px 10px;border-radius:6px;transition:color var(--t),background var(--t)}
.sofy-nav-link:hover,.sofy-nav-link.active{color:var(--accent);background:rgba(217,119,87,.08)}
.sofy-nav-spacer{flex:1}
.sofy-nav-actions{display:flex;gap:8px;align-items:center}

/* page header */
.sofy-page-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px}
.sofy-page-title{font-size:20px;font-weight:700;color:var(--bright);letter-spacing:-.02em}
.sofy-page-actions{display:flex;gap:8px;align-items:center}

/* card */
.sofy-card{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);margin-bottom:20px;box-shadow:var(--shadow)}
.sofy-card-hdr{display:flex;align-items:center;justify-content:space-between;padding:13px 18px;border-bottom:1px solid var(--border)}
.sofy-card-title{font-size:10px;font-weight:600;color:var(--bright);letter-spacing:.08em;text-transform:uppercase}
.sofy-card-body{padding:18px}
.sofy-card-ftr{padding:11px 18px;border-top:1px solid var(--border);font-size:11px;color:var(--muted)}

/* grid */
.sofy-grid{display:grid;gap:16px;margin-bottom:20px}
.sofy-g1{grid-template-columns:1fr}
.sofy-g2{grid-template-columns:repeat(2,1fr)}
.sofy-g3{grid-template-columns:repeat(3,1fr)}
.sofy-g4{grid-template-columns:repeat(4,1fr)}
@media(max-width:720px){.sofy-g2,.sofy-g3,.sofy-g4{grid-template-columns:1fr}}

/* stat */
.sofy-stat{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);padding:18px 20px;box-shadow:var(--shadow)}
.sofy-stat-lbl{font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:8px}
.sofy-stat-val{font-size:28px;font-weight:700;color:var(--bright);line-height:1;margin-bottom:6px}
.sofy-stat-trend{font-size:11px}
.sofy-stat-trend.up{color:var(--success)}
.sofy-stat-trend.dn{color:var(--danger)}
.sofy-stat-desc{font-size:11px;color:var(--muted);margin-top:4px}

/* table */
.sofy-tbl-wrap{overflow-x:auto}
.sofy-tbl{width:100%;border-collapse:collapse}
.sofy-tbl th{text-align:left;padding:9px 13px;font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border);font-weight:600;white-space:nowrap}
.sofy-tbl td{padding:10px 13px;border-bottom:1px solid var(--border);font-size:12px;color:var(--text);vertical-align:middle}
.sofy-tbl tr:last-child td{border-bottom:none}
.sofy-tbl tbody tr:hover td{background:rgba(0,0,0,.022)}
.sofy-tbl-empty{text-align:center;padding:36px;color:var(--muted);font-size:12px}
.sofy-tbl-actions{display:flex;gap:6px;align-items:center}

/* alert */
.sofy-alert{border-radius:var(--r);padding:11px 15px;margin-bottom:14px;font-size:12px;display:flex;align-items:flex-start;gap:10px;border:1px solid}
.sofy-alert-success{background:rgba(90,158,111,.07);border-color:rgba(90,158,111,.28);color:#82c090}
.sofy-alert-warning{background:rgba(192,152,72,.07);border-color:rgba(192,152,72,.28);color:#c0a464}
.sofy-alert-danger{background:rgba(192,120,72,.07);border-color:rgba(192,120,72,.28);color:#c08464}
.sofy-alert-info{background:rgba(92,142,191,.07);border-color:rgba(92,142,191,.28);color:#7aafd0}
.sofy-alert-icon{font-size:13px;flex-shrink:0;margin-top:1px}
.sofy-alert-title{font-weight:600;margin-bottom:2px}

/* badge */
.sofy-badge{display:inline-flex;align-items:center;font-size:10px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;padding:2px 7px;border-radius:4px;border:1px solid;white-space:nowrap}
.sofy-badge-default{background:rgba(168,156,141,.18);border-color:rgba(168,156,141,.32);color:var(--muted)}
.sofy-badge-success{background:rgba(90,158,111,.13);border-color:rgba(90,158,111,.3);color:#82c090}
.sofy-badge-warning{background:rgba(192,152,72,.13);border-color:rgba(192,152,72,.3);color:#c0a464}
.sofy-badge-danger{background:rgba(192,120,72,.13);border-color:rgba(192,120,72,.3);color:#c08464}
.sofy-badge-info{background:rgba(92,142,191,.13);border-color:rgba(92,142,191,.3);color:#7aafd0}
.sofy-badge-accent{background:rgba(217,119,87,.13);border-color:rgba(217,119,87,.3);color:var(--accent)}

/* button */
.sofy-btn{display:inline-flex;align-items:center;gap:6px;font-size:11px;letter-spacing:.06em;text-transform:uppercase;padding:8px 18px;border-radius:12px;text-decoration:none;border:1px solid transparent;cursor:pointer;transition:background var(--t),border-color var(--t),color var(--t),box-shadow var(--t),transform var(--t);font-family:var(--font);white-space:nowrap;line-height:1}
.sofy-btn-primary{background:var(--accent);border-color:var(--accent);color:#fff;box-shadow:0 3px 10px rgba(217,119,87,.25)}
.sofy-btn-primary:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(217,119,87,.3)}
.sofy-btn-primary:hover{background:#c4633f;border-color:#c4633f}
.sofy-btn-ghost{background:var(--surf);border-color:var(--border);color:var(--accent)}
.sofy-btn-ghost:hover{border-color:var(--accent)}
.sofy-btn-danger{background:rgba(192,120,72,.08);border-color:rgba(192,120,72,.32);color:var(--danger)}
.sofy-btn-danger:hover{background:rgba(192,120,72,.18)}
.sofy-btn-success{background:rgba(90,158,111,.08);border-color:rgba(90,158,111,.32);color:var(--success)}
.sofy-btn-success:hover{background:rgba(90,158,111,.18)}
.sofy-btn-warning{background:rgba(192,152,72,.08);border-color:rgba(192,152,72,.32);color:var(--warning)}
.sofy-btn-warning:hover{background:rgba(192,152,72,.18)}
.sofy-btn-sm{padding:5px 11px;font-size:10px}
.sofy-btn-lg{padding:11px 26px;font-size:13px}
.sofy-btn-group{display:flex;gap:8px;flex-wrap:wrap;align-items:center}

/* hero */
.sofy-hero{padding:44px 0 32px;text-align:center}
.sofy-hero-title{font-size:clamp(26px,5vw,50px);font-weight:800;color:var(--bright);line-height:1.1;margin-bottom:12px;letter-spacing:-.03em}
.sofy-hero-sub{font-size:14px;color:var(--muted);max-width:460px;margin:0 auto 26px;line-height:1.9}
.sofy-hero-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}

/* tabs */
.sofy-tabs{margin-bottom:20px}
.sofy-tab-list{display:flex;border-bottom:1px solid var(--border);margin-bottom:18px;overflow-x:auto;gap:0}
.sofy-tab-btn{background:none;border:none;border-bottom:2px solid transparent;padding:8px 16px;margin-bottom:-1px;cursor:pointer;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);transition:color var(--t),border-color var(--t);font-family:var(--font);white-space:nowrap}
.sofy-tab-btn.active,.sofy-tab-btn:hover{color:var(--accent)}
.sofy-tab-btn.active{border-bottom-color:var(--accent)}
.sofy-tab-panel{display:none}
.sofy-tab-panel.active{display:block}

/* form */
.sofy-form-row{margin-bottom:16px}
.sofy-form-label{display:block;font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:6px}
.sofy-form-req{color:var(--danger);margin-left:2px}
.sofy-form-ctrl{width:100%;background:var(--surf2);border:1px solid var(--border);border-radius:8px;padding:9px 13px;color:var(--text);font-family:var(--font);font-size:13px;outline:none;transition:border-color var(--t),box-shadow var(--t);appearance:none}
.sofy-form-ctrl:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(217,119,87,.1)}
.sofy-form-ctrl::placeholder{color:var(--muted);opacity:.6}
textarea.sofy-form-ctrl{resize:vertical;min-height:96px}
.sofy-form-hint{font-size:11px;color:var(--muted);margin-top:4px}
.sofy-form-err{font-size:11px;color:var(--danger);margin-top:4px}
.sofy-form-check{display:flex;align-items:center;gap:10px;cursor:pointer}
.sofy-form-check-cb{width:15px;height:15px;accent-color:var(--accent);cursor:pointer;flex-shrink:0}
.sofy-form-check-lbl{font-size:12px;color:var(--text);cursor:pointer}
.sofy-form-footer{margin-top:22px;display:flex;gap:10px;align-items:center}
.sofy-form-cols{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:560px){.sofy-form-cols{grid-template-columns:1fr}}

/* content */
.sofy-h{color:var(--bright);font-weight:700;line-height:1.25;margin-bottom:12px}
.sofy-h1{font-size:30px}.sofy-h2{font-size:22px}.sofy-h3{font-size:17px}
.sofy-h4{font-size:14px}.sofy-h5,.sofy-h6{font-size:13px}
.sofy-p{font-size:13px;color:var(--text);margin-bottom:12px;line-height:1.9}
.sofy-muted{color:var(--muted)}
.sofy-code-wrap{margin-bottom:14px}
.sofy-code-lang{font-size:10px;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;margin-bottom:6px}
.sofy-code{background:var(--surf2);border:1px solid var(--border);border-radius:12px;padding:14px 18px;font-family:var(--mono);font-size:12px;color:var(--text);overflow-x:auto;white-space:pre;line-height:1.7;display:block}
.sofy-ul,.sofy-ol{padding-left:20px;margin-bottom:12px}
.sofy-ul li,.sofy-ol li{padding:2px 0;font-size:13px;color:var(--text)}
.sofy-hr{border:none;border-top:1px solid var(--border);margin:22px 0}

/* scroll area */
.sofy-scroll{border-radius:var(--r)}
.sofy-scroll-vertical{overflow-y:auto;overflow-x:hidden}
.sofy-scroll-horizontal{overflow-x:auto;overflow-y:hidden;max-width:100%}
.sofy-scroll-both{overflow:auto}
.sofy-scroll::-webkit-scrollbar{width:5px;height:5px}
.sofy-scroll::-webkit-scrollbar-track{background:transparent}
.sofy-scroll::-webkit-scrollbar-thumb{background:var(--border);border-radius:100px;transition:background var(--t)}
.sofy-scroll::-webkit-scrollbar-thumb:hover{background:var(--accent2)}
.sofy-scroll{scrollbar-width:thin;scrollbar-color:var(--border) transparent}

/* breadcrumb */
.sofy-bc{display:flex;list-style:none;align-items:center;flex-wrap:wrap;gap:0;margin-bottom:16px;font-size:11px;padding:0}
.sofy-bc li+li::before{content:'/';margin:0 8px;color:var(--muted)}
.sofy-bc a{color:var(--muted);text-decoration:none;transition:color var(--t)}
.sofy-bc a:hover{color:var(--accent)}
.sofy-bc li:last-child{color:var(--text)}

/* pagination */
.sofy-pages{display:flex;align-items:center;gap:4px;flex-wrap:wrap;margin-bottom:20px}
.sofy-page-btn{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;border-radius:6px;font-size:11px;text-decoration:none;color:var(--muted);border:1px solid var(--border);background:var(--surf);transition:all var(--t);font-family:var(--font)}
.sofy-page-btn:hover{color:var(--accent);border-color:var(--accent)}
.sofy-page-btn.active{background:var(--accent);border-color:var(--accent);color:#fff}
.sofy-page-btn.disabled{opacity:.35;pointer-events:none}
.sofy-page-dots{color:var(--muted);font-size:11px;padding:0 4px}

/* progress */
.sofy-progress{margin-bottom:16px}
.sofy-progress-hdr{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;font-size:10px;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
.sofy-progress-track{background:var(--surf2);border-radius:100px;overflow:hidden;border:1px solid var(--border)}
.sofy-progress-track.sm{height:4px}.sofy-progress-track.md{height:8px}.sofy-progress-track.lg{height:14px}
.sofy-progress-fill{height:100%;border-radius:100px;transition:width .4s ease}
.sofy-progress-fill.accent{background:var(--accent)}
.sofy-progress-fill.success{background:var(--success)}
.sofy-progress-fill.warning{background:var(--warning)}
.sofy-progress-fill.danger{background:var(--danger)}
.sofy-progress-fill.info{background:var(--info)}

/* key-value */
.sofy-kv{margin-bottom:16px;padding:0}
.sofy-kv-row{display:flex;padding:9px 0;border-bottom:1px solid var(--border)}
.sofy-kv-row:last-child{border-bottom:none}
.sofy-kv.stacked .sofy-kv-row{flex-direction:column;gap:4px}
.sofy-kv.inline .sofy-kv-row{justify-content:space-between;align-items:center;gap:16px}
.sofy-kv-key{font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);flex-shrink:0}
.sofy-kv-val{font-size:12px;color:var(--text)}

/* accordion */
.sofy-accordion{border:1px solid var(--border);border-radius:var(--r);overflow:hidden;margin-bottom:20px}
.sofy-accordion details{border-bottom:1px solid var(--border)}
.sofy-accordion details:last-child{border-bottom:none}
.sofy-accordion summary{list-style:none;padding:12px 16px;cursor:pointer;font-size:12px;font-weight:600;color:var(--text);display:flex;justify-content:space-between;align-items:center;user-select:none;transition:background var(--t)}
.sofy-accordion summary::-webkit-details-marker{display:none}
.sofy-accordion summary::after{content:'›';font-size:16px;color:var(--muted);transition:transform .2s;line-height:1;flex-shrink:0}
.sofy-accordion details[open]>summary::after{transform:rotate(90deg)}
.sofy-accordion summary:hover{background:rgba(0,0,0,.028)}
.sofy-accordion-body{padding:0 16px 14px;font-size:12px;color:var(--text);line-height:1.8}

/* empty state */
.sofy-empty{text-align:center;padding:52px 24px;color:var(--muted)}
.sofy-empty-icon{font-size:40px;margin-bottom:14px;opacity:.25;line-height:1}
.sofy-empty-title{font-size:14px;font-weight:600;color:var(--text);margin-bottom:6px}
.sofy-empty-desc{font-size:12px;margin-bottom:20px;line-height:1.7}
.sofy-empty-action{display:flex;justify-content:center;gap:8px}

/* avatar */
.sofy-avatar{display:inline-flex;align-items:center;justify-content:center;border-radius:50%;font-weight:700;flex-shrink:0;letter-spacing:.02em;object-fit:cover}
.sofy-avatar-sm{width:26px;height:26px;font-size:9px}
.sofy-avatar-md{width:36px;height:36px;font-size:13px}
.sofy-avatar-lg{width:48px;height:48px;font-size:17px}
.sofy-avatar-xl{width:64px;height:64px;font-size:22px}
.sofy-avatar-accent{background:rgba(217,119,87,.18);color:var(--accent)}
.sofy-avatar-success{background:rgba(90,158,111,.18);color:var(--success)}
.sofy-avatar-warning{background:rgba(192,152,72,.18);color:var(--warning)}
.sofy-avatar-danger{background:rgba(192,120,72,.18);color:var(--danger)}
.sofy-avatar-info{background:rgba(92,142,191,.18);color:var(--info)}
.sofy-avatar-muted{background:rgba(168,156,141,.18);color:var(--muted)}

/* timeline */
.sofy-timeline{position:relative;padding-left:26px;margin-bottom:20px}
.sofy-timeline::before{content:'';position:absolute;left:7px;top:10px;bottom:10px;width:1px;background:var(--border)}
.sofy-tl-item{position:relative;margin-bottom:22px}
.sofy-tl-item:last-child{margin-bottom:0}
.sofy-tl-dot{position:absolute;left:-26px;top:3px;width:15px;height:15px;border-radius:50%;border:2px solid var(--border);background:var(--bg)}
.sofy-tl-dot-accent{border-color:var(--accent);background:rgba(217,119,87,.12)}
.sofy-tl-dot-success{border-color:var(--success);background:rgba(90,158,111,.12)}
.sofy-tl-dot-warning{border-color:var(--warning);background:rgba(192,152,72,.12)}
.sofy-tl-dot-danger{border-color:var(--danger);background:rgba(192,120,72,.12)}
.sofy-tl-dot-info{border-color:var(--info);background:rgba(92,142,191,.12)}
.sofy-tl-hdr{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:4px}
.sofy-tl-title{font-size:12px;font-weight:600;color:var(--text)}
.sofy-tl-time{font-size:10px;color:var(--muted);white-space:nowrap;letter-spacing:.04em}
.sofy-tl-content{font-size:12px;color:var(--muted);line-height:1.7}

/* steps */
.sofy-steps{display:flex;align-items:flex-start;margin-bottom:24px;overflow-x:auto}
.sofy-step{flex:1;display:flex;flex-direction:column;align-items:center;position:relative;min-width:72px}
.sofy-step+.sofy-step::before{content:'';position:absolute;top:13px;right:50%;left:-50%;height:1px;background:var(--border)}
.sofy-step.done+.sofy-step::before,.sofy-step.active+.sofy-step::before{background:var(--accent)}
.sofy-step-dot{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;border:2px solid var(--border);background:var(--bg);position:relative;z-index:1;transition:all var(--t)}
.sofy-step.done .sofy-step-dot{background:var(--accent);border-color:var(--accent);color:#fff}
.sofy-step.active .sofy-step-dot{border-color:var(--accent);color:var(--accent)}
.sofy-step.pending .sofy-step-dot{color:var(--muted)}
.sofy-step-label{font-size:10px;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin-top:8px;text-align:center;line-height:1.4}
.sofy-step.active .sofy-step-label{color:var(--accent)}
.sofy-step.done .sofy-step-label{color:var(--text)}

/* spinner */
@keyframes sofy-spin{to{transform:rotate(360deg)}}
.sofy-spinner{display:inline-block;border-radius:50%;border:2px solid transparent;animation:sofy-spin .65s linear infinite;flex-shrink:0;vertical-align:middle}
.sofy-spinner-sm{width:14px;height:14px}
.sofy-spinner-md{width:22px;height:22px}
.sofy-spinner-lg{width:36px;height:36px;border-width:3px}
.sofy-spinner-accent{border-top-color:var(--accent);border-right-color:rgba(217,119,87,.15);border-bottom-color:rgba(217,119,87,.15);border-left-color:rgba(217,119,87,.15)}
.sofy-spinner-muted{border-top-color:var(--muted);border-right-color:rgba(168,156,141,.15);border-bottom-color:rgba(168,156,141,.15);border-left-color:rgba(168,156,141,.15)}
.sofy-spinner-white{border-top-color:#fff;border-right-color:rgba(255,255,255,.15);border-bottom-color:rgba(255,255,255,.15);border-left-color:rgba(255,255,255,.15)}

/* form — radio + toggle + file */
.sofy-radio-group{display:flex;flex-direction:column;gap:9px}
.sofy-radio{display:flex;align-items:center;gap:10px;cursor:pointer}
.sofy-radio-cb{width:15px;height:15px;accent-color:var(--accent);cursor:pointer;flex-shrink:0}
.sofy-radio-lbl{font-size:12px;color:var(--text)}
.sofy-toggle{display:inline-flex;align-items:center;gap:10px;cursor:pointer}
.sofy-toggle-cb{position:absolute;opacity:0;width:0;height:0;pointer-events:none}
.sofy-toggle-track{width:38px;height:22px;background:var(--surf2);border:1px solid var(--border);border-radius:100px;position:relative;transition:background var(--t),border-color var(--t);flex-shrink:0}
.sofy-toggle-cb:checked+.sofy-toggle-track{background:var(--accent);border-color:var(--accent)}
.sofy-toggle-thumb{width:16px;height:16px;background:var(--muted);border-radius:50%;position:absolute;top:2px;left:2px;transition:transform var(--t),background var(--t)}
.sofy-toggle-cb:checked+.sofy-toggle-track .sofy-toggle-thumb{transform:translateX(16px);background:#fff}
.sofy-toggle-lbl{font-size:12px;color:var(--text)}
.sofy-form-file{width:100%;background:var(--surf2);border:1px solid var(--border);border-radius:8px;padding:8px 12px;color:var(--text);font-family:var(--font);font-size:12px;cursor:pointer;transition:border-color var(--t)}
.sofy-form-file:hover{border-color:var(--accent)}
.sofy-form-file::file-selector-button{background:var(--surf);border:1px solid var(--border);border-radius:6px;padding:4px 10px;color:var(--accent);font-size:10px;font-family:var(--font);cursor:pointer;margin-right:10px;text-transform:uppercase;letter-spacing:.06em;transition:background var(--t)}
.sofy-form-file::file-selector-button:hover{background:var(--surf2)}

/* footer */
.sofy-footer{text-align:center;padding:22px;font-size:11px;color:var(--muted);border-top:1px solid var(--border);letter-spacing:.06em}
.sofy-footer span{color:var(--accent)}

/* ── dialog / modal ──────────────────────────────────────────────────────── */
dialog.sofy-dialog{background:var(--surf);border:1px solid var(--border);border-radius:var(--r);padding:0;max-width:var(--dlg-w,520px);width:calc(100% - 40px);color:var(--text);outline:none}
dialog.sofy-dialog::backdrop{background:rgba(0,0,0,.65);backdrop-filter:blur(4px)}
dialog.sofy-dialog[open]{animation:none}
@keyframes sofy-dlg-in{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.sofy-dialog-sm{--dlg-w:360px}.sofy-dialog-lg{--dlg-w:720px}.sofy-dialog-xl{--dlg-w:920px}
.sofy-dialog-form{display:flex;flex-direction:column;max-height:calc(100vh - 80px)}
.sofy-dialog-hdr{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border);flex-shrink:0}
.sofy-dialog-title{font-size:13px;font-weight:700;color:var(--bright);letter-spacing:-.01em}
.sofy-dialog-close{background:none;border:none;color:var(--muted);cursor:pointer;font-size:15px;padding:0;line-height:1;transition:color var(--t)}
.sofy-dialog-close:hover{color:var(--text)}
.sofy-dialog-body{padding:18px;overflow-y:auto;flex:1}
.sofy-dialog-ftr{padding:12px 18px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;flex-shrink:0}

/* ── toast ───────────────────────────────────────────────────────────────── */
.sofy-toast-tray{position:fixed;top:20px;right:20px;z-index:200;display:flex;flex-direction:column;gap:10px;max-width:340px;pointer-events:none}
.sofy-toast{display:flex;align-items:flex-start;gap:10px;padding:11px 14px;border-radius:var(--r);border:1px solid;box-shadow:0 8px 30px rgba(0,0,0,.35);pointer-events:all;animation:sofy-toast-in .22s ease;transition:opacity .3s,transform .3s}
@keyframes sofy-toast-in{from{opacity:0;transform:translateX(16px)}to{opacity:1;transform:translateX(0)}}
.sofy-toast-success{background:rgba(90,158,111,.06);border-color:rgba(90,158,111,.35)}
.sofy-toast-warning{background:rgba(192,152,72,.06);border-color:rgba(192,152,72,.35)}
.sofy-toast-danger{background:rgba(192,120,72,.06);border-color:rgba(192,120,72,.35)}
.sofy-toast-info{background:rgba(92,142,191,.06);border-color:rgba(92,142,191,.35)}
.sofy-toast-icon{flex-shrink:0;font-size:13px;margin-top:1px}
.sofy-toast-success .sofy-toast-icon{color:var(--success)}
.sofy-toast-warning .sofy-toast-icon{color:var(--warning)}
.sofy-toast-danger  .sofy-toast-icon{color:var(--danger)}
.sofy-toast-info    .sofy-toast-icon{color:var(--info)}
.sofy-toast-body{flex:1;min-width:0}
.sofy-toast-title{font-size:11px;font-weight:600;color:var(--bright);margin-bottom:2px}
.sofy-toast-msg{font-size:12px;color:var(--text)}
.sofy-toast-close{background:none;border:none;color:var(--muted);cursor:pointer;font-size:12px;padding:0;flex-shrink:0;align-self:center;transition:color var(--t)}
.sofy-toast-close:hover{color:var(--text)}

/* ── drawer ──────────────────────────────────────────────────────────────── */
.sofy-drawer{position:fixed;inset:0;z-index:150;pointer-events:none;display:flex}
.sofy-drawer-right{justify-content:flex-end}
.sofy-drawer-left{justify-content:flex-start}
.sofy-drawer-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);opacity:0;transition:opacity .25s;pointer-events:none}
.sofy-drawer-panel{position:relative;background:var(--surf);border-left:1px solid var(--border);height:100%;display:flex;flex-direction:column;transform:translateX(100%);filter:blur(var(--panel-blur));transition:transform var(--panel-open-dur) var(--panel-ease),filter var(--panel-open-dur) var(--panel-ease);pointer-events:all}
.sofy-drawer-left .sofy-drawer-panel{border-left:none;border-right:1px solid var(--border);transform:translateX(-100%)}
.sofy-drawer.open .sofy-drawer-backdrop{opacity:1;pointer-events:all}
.sofy-drawer.open .sofy-drawer-panel{transform:translateX(0);filter:blur(0)}
.sofy-drawer-hdr{display:flex;align-items:center;justify-content:space-between;padding:15px 20px;border-bottom:1px solid var(--border);flex-shrink:0}
.sofy-drawer-title{font-size:13px;font-weight:700;color:var(--bright)}
.sofy-drawer-close{background:none;border:none;color:var(--muted);cursor:pointer;font-size:15px;padding:0;transition:color var(--t)}
.sofy-drawer-close:hover{color:var(--text)}
.sofy-drawer-body{flex:1;overflow-y:auto;padding:20px;scrollbar-width:thin;scrollbar-color:var(--border) transparent}
.sofy-drawer-ftr{padding:14px 20px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;flex-shrink:0}

/* ── tooltip ─────────────────────────────────────────────────────────────── */
.sofy-tip{position:relative;display:inline-flex;align-items:center}
.sofy-tip::before,.sofy-tip::after{position:absolute;opacity:0;pointer-events:none;transition:opacity .15s,transform .15s;white-space:nowrap;z-index:300}
.sofy-tip::after{content:attr(data-tip);background:var(--surf2);border:1px solid var(--border);color:var(--text);font-size:11px;padding:5px 10px;border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,.3)}
.sofy-tip::before{content:'';border:5px solid transparent}
.sofy-tip-top::after{bottom:calc(100% + 8px);left:50%;transform:translateX(-50%) translateY(4px)}
.sofy-tip-top::before{bottom:100%;left:50%;transform:translateX(-50%) translateY(4px);border-top-color:var(--border)}
.sofy-tip-top:hover::after,.sofy-tip-top:hover::before{opacity:1;transform:translateX(-50%) translateY(0)}
.sofy-tip-bottom::after{top:calc(100% + 8px);left:50%;transform:translateX(-50%) translateY(-4px)}
.sofy-tip-bottom::before{top:100%;left:50%;transform:translateX(-50%) translateY(-4px);border-bottom-color:var(--border)}
.sofy-tip-bottom:hover::after,.sofy-tip-bottom:hover::before{opacity:1;transform:translateX(-50%) translateY(0)}
.sofy-tip-right::after{left:calc(100% + 8px);top:50%;transform:translateY(-50%) translateX(-4px)}
.sofy-tip-right::before{left:100%;top:50%;transform:translateY(-50%) translateX(-4px);border-right-color:var(--border)}
.sofy-tip-right:hover::after,.sofy-tip-right:hover::before{opacity:1;transform:translateY(-50%) translateX(0)}
.sofy-tip-left::after{right:calc(100% + 8px);top:50%;transform:translateY(-50%) translateX(4px)}
.sofy-tip-left::before{right:100%;top:50%;transform:translateY(-50%) translateX(4px);border-left-color:var(--border)}
.sofy-tip-left:hover::after,.sofy-tip-left:hover::before{opacity:1;transform:translateY(-50%) translateX(0)}

/* ── chart ───────────────────────────────────────────────────────────────── */
.sofy-chart{margin-bottom:20px;position:relative}
.sofy-chart-empty{text-align:center;padding:40px;color:var(--muted);font-size:12px}
.sofy-chart-bar-wrap{display:flex;align-items:flex-end;gap:6px;padding-bottom:36px;position:relative}
.sofy-chart-bar-wrap::after{content:'';position:absolute;left:0;right:0;bottom:36px;height:1px;background:var(--border)}
.sofy-chart-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;min-width:0}
.sofy-chart-bar-val{font-size:10px;color:var(--muted);white-space:nowrap}
.sofy-chart-bar-inner{flex:1;width:100%;display:flex;align-items:flex-end}
.sofy-chart-bar{width:100%;min-height:3px;border-radius:4px 4px 0 0;transition:opacity .15s}
.sofy-chart-bar:hover{opacity:.75}
.sofy-chart-bar-lbl{font-size:10px;color:var(--muted);width:100%;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sofy-chart-svg{width:100%;height:auto;display:block}
.sofy-chart-grid{stroke:var(--border);stroke-width:1}
.sofy-chart-axis-lbl{fill:var(--muted);font-size:11px;font-family:var(--font)}
.sofy-chart-dot{transition:r .15s;cursor:pointer}
.sofy-chart-dot:hover{r:5}
.sofy-chart-slice{transition:opacity .15s;cursor:pointer}
.sofy-chart-slice:hover{opacity:.8}
.sofy-chart-center-val{fill:var(--bright);font-size:20px;font-weight:700;font-family:var(--font)}
.sofy-chart-center-lbl{fill:var(--muted);font-size:11px;font-family:var(--font)}
.sofy-chart-pie-wrap{display:flex;align-items:center;gap:24px;flex-wrap:wrap}
.sofy-chart-pie-wrap .sofy-chart-svg{max-width:200px;flex-shrink:0}
.sofy-chart-legend{display:flex;flex-direction:column;gap:8px;flex:1;min-width:120px}
.sofy-chart-legend-item{display:flex;align-items:center;gap:8px;font-size:11px}
.sofy-chart-legend-dot{width:10px;height:10px;border-radius:2px;flex-shrink:0}
.sofy-chart-legend-lbl{flex:1;color:var(--text)}
.sofy-chart-legend-val{color:var(--muted)}

/* ── datatable ───────────────────────────────────────────────────────────── */
.sofy-dt{margin-bottom:20px}
.sofy-dt-toolbar{margin-bottom:12px}
.sofy-dt-search{max-width:280px}
.sofy-dt-th{cursor:pointer;user-select:none;white-space:nowrap}
.sofy-dt-th:hover{color:var(--accent)}
.sofy-dt-th[data-sort="asc"] .sofy-dt-sort::after{content:'↑';color:var(--accent)}
.sofy-dt-th[data-sort="desc"] .sofy-dt-sort::after{content:'↓';color:var(--accent)}
.sofy-dt-sort{color:var(--muted);font-size:10px;margin-left:4px}
.sofy-dt-footer{display:flex;align-items:center;justify-content:space-between;margin-top:10px;flex-wrap:wrap;gap:8px}
.sofy-dt-info{font-size:11px;color:var(--muted)}
.sofy-dt-pager{display:flex;gap:4px;flex-wrap:wrap}
.sofy-dt-row.hidden{display:none}

/* ── sidebar layout ──────────────────────────────────────────────────────── */
.sofy-sl{display:flex;align-items:flex-start;margin-bottom:20px}
.sofy-sl-sidebar{position:sticky;top:70px}
.sofy-sl-main{flex:1;min-width:0}
@media(max-width:720px){.sofy-sl{flex-direction:column}.sofy-sl-sidebar{width:100%!important;position:static}}

/* ── command palette ─────────────────────────────────────────────────────── */
.sofy-cmd{position:fixed;inset:0;z-index:500;display:flex;align-items:flex-start;justify-content:center;padding-top:100px}
.sofy-cmd[hidden]{display:none}
.sofy-cmd-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.72);backdrop-filter:blur(6px)}
.sofy-cmd-panel{position:relative;background:var(--surf);border:1px solid var(--border);border-radius:14px;width:min(580px,calc(100% - 40px));box-shadow:0 25px 80px rgba(0,0,0,.6);overflow:hidden;display:flex;flex-direction:column;max-height:70vh;animation:sofy-dlg-in .18s ease}
.sofy-cmd-search-wrap{display:flex;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid var(--border);flex-shrink:0}
.sofy-cmd-search-icon{font-size:16px;color:var(--muted);flex-shrink:0}
.sofy-cmd-input{flex:1;background:none;border:none;outline:none;font-family:var(--font);font-size:14px;color:var(--text)}
.sofy-cmd-input::placeholder{color:var(--muted);opacity:.6}
.sofy-cmd-esc{font-family:var(--font);font-size:10px;background:var(--surf2);border:1px solid var(--border);border-radius:5px;padding:2px 7px;color:var(--muted);cursor:pointer;flex-shrink:0}
.sofy-cmd-list{overflow-y:auto;padding:6px;flex:1;scrollbar-width:thin;scrollbar-color:var(--border) transparent}
.sofy-cmd-group-lbl{font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);padding:10px 10px 4px;font-weight:600}
.sofy-cmd-item{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:8px;text-decoration:none;color:var(--text);font-size:13px;transition:background var(--t)}
.sofy-cmd-item:hover,.sofy-cmd-item.focused{background:rgba(217,119,87,.08);color:var(--bright)}
.sofy-cmd-item-icon{font-size:14px;width:20px;text-align:center;flex-shrink:0;color:var(--muted)}
.sofy-cmd-item-icon-def{opacity:.3}
.sofy-cmd-item-lbl{flex:1}
.sofy-cmd-item-sc{font-family:var(--font);font-size:10px;background:var(--surf2);border:1px solid var(--border);border-radius:4px;padding:2px 6px;color:var(--muted)}
.sofy-cmd-item.hidden{display:none}
.sofy-cmd-footer{padding:9px 16px;border-top:1px solid var(--border);font-size:10px;color:var(--muted);display:flex;gap:14px;flex-shrink:0}
.sofy-cmd-footer kbd{background:var(--surf2);border:1px solid var(--border);border-radius:4px;padding:1px 5px;font-family:var(--font);font-size:10px}

/* ── debug bar ───────────────────────────────────────────────────────────── */
.sofy-dbg{position:fixed;bottom:0;left:0;right:0;z-index:1000;background:rgba(255,250,245,.92);border-top:1px solid var(--border);backdrop-filter:blur(8px)}
.sofy-dbg-inner{display:flex;align-items:center;gap:6px;padding:5px 16px;flex-wrap:wrap;min-height:34px}
.sofy-dbg-pill{display:inline-flex;align-items:center;font-size:10px;padding:3px 9px;border-radius:100px;letter-spacing:.04em;font-family:var(--font);white-space:nowrap}
.sofy-dbg-brand{background:rgba(217,119,87,.15);color:var(--accent);font-weight:700;letter-spacing:.08em}
.sofy-dbg-info{background:rgba(168,156,141,.2);color:var(--muted)}
.sofy-dbg-success{background:rgba(90,158,111,.15);color:var(--success)}
.sofy-dbg-warning{background:rgba(192,152,72,.15);color:var(--warning)}
.sofy-dbg-danger{background:rgba(192,120,72,.15);color:var(--danger)}
.sofy-dbg-spacer{flex:1}
.sofy-dbg-req{display:flex;align-items:center;gap:6px;font-size:10px}
.sofy-dbg-method{padding:2px 7px;border-radius:4px;font-weight:700;font-size:9px;letter-spacing:.08em;text-transform:uppercase}
.sofy-dbg-method-get{background:rgba(90,158,111,.15);color:var(--success)}
.sofy-dbg-method-post{background:rgba(217,119,87,.15);color:var(--accent)}
.sofy-dbg-method-put,.sofy-dbg-method-patch{background:rgba(192,152,72,.15);color:var(--warning)}
.sofy-dbg-method-delete{background:rgba(192,120,72,.15);color:var(--danger)}
.sofy-dbg-path{color:var(--muted);font-size:11px;font-family:var(--font)}
.sofy-dbg-close{background:none;border:none;color:var(--muted);cursor:pointer;font-size:12px;padding:0 0 0 10px;transition:color var(--t)}
.sofy-dbg-close:hover{color:var(--text)}

/* ── Dark theme (warm) ─────────────────────────────────────────────────────── */
[data-theme="dark"]{
    --bg:#1a1613;--surf:#241e1a;--surf2:#2d2622;
    --border:#3a322c;--text:#e9ddd0;--muted:#a08f7e;--bright:#fdf6ee;
    --accent:#e8896b;--accent2:#bf9ee0;
    --shadow:0 4px 18px rgba(0,0,0,.42),0 1px 3px rgba(0,0,0,.32);
    --shadow-lg:0 14px 44px rgba(0,0,0,.5)
}
[data-theme="dark"] body{background:var(--bg);color:var(--text)}
[data-theme="dark"] body::before{opacity:.5}
[data-theme="dark"] .sofy-moon{opacity:.5}
[data-theme="dark"] .sofy-nav{background:rgba(26,22,19,.82);border-bottom-color:var(--border)}
[data-theme="dark"] .sofy-dbg{background:rgba(26,22,19,.95)}
[data-theme="dark"] .sofy-tbl tbody tr:hover td{background:rgba(255,255,255,.03)}
[data-theme="dark"] .sofy-accordion summary:hover{background:rgba(255,255,255,.03)}
[data-theme="dark"] .sofy-locale-btn:hover{background:rgba(255,255,255,.05)}
[data-theme="dark"] .sofy-surf{background:var(--surf)}
[data-theme="dark"] .sofy-footer{border-top-color:var(--border)}

/* ── Theme toggle button ──────────────────────────────────────────────────── */
.sofy-theme-btn{
    background:none;border:1px solid var(--border);border-radius:6px;
    color:var(--muted);cursor:pointer;font-size:15px;line-height:1;
    padding:4px 8px;margin-left:8px;
    transition:background var(--t),border-color var(--t),color var(--t)
}
.sofy-theme-btn:hover{background:var(--surf2);color:var(--text)}
.sofy-theme-btn .t-icon-swap{font-size:15px;line-height:1}
.sofy-theme-btn .t-icon{line-height:1}

/* ── tag / chip ─────────────────────────────────────────────────────────── */
.sofy-tag{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:500;letter-spacing:.03em;padding:3px 9px;border-radius:100px;border:1px solid;white-space:nowrap;transition:opacity var(--t);cursor:default;text-decoration:none}
a.sofy-tag:hover{opacity:.75}
.sofy-tag-default{background:rgba(168,156,141,.12);border-color:rgba(168,156,141,.28);color:var(--muted)}
.sofy-tag-success{background:rgba(90,158,111,.1);border-color:rgba(90,158,111,.28);color:#82c090}
.sofy-tag-warning{background:rgba(192,152,72,.1);border-color:rgba(192,152,72,.28);color:#c0a464}
.sofy-tag-danger{background:rgba(192,120,72,.1);border-color:rgba(192,120,72,.28);color:#c08464}
.sofy-tag-info{background:rgba(92,142,191,.1);border-color:rgba(92,142,191,.28);color:#7aafd0}
.sofy-tag-accent{background:rgba(217,119,87,.1);border-color:rgba(217,119,87,.28);color:var(--accent)}
.sofy-tag-rm{background:none;border:none;cursor:pointer;color:inherit;font-size:14px;line-height:1;padding:0;margin-left:1px;opacity:.55;transition:opacity var(--t);flex-shrink:0;display:inline-flex;align-items:center}
.sofy-tag-rm:hover{opacity:1}
.sofy-tags{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-bottom:12px}

/* ── banner ──────────────────────────────────────────────────────────────── */
.sofy-banner{display:flex;align-items:center;gap:10px;padding:11px 16px;border-radius:var(--r);border:1px solid;margin-bottom:16px;font-size:12px}
.sofy-banner-info{background:rgba(92,142,191,.07);border-color:rgba(92,142,191,.28);color:#7aafd0}
.sofy-banner-success{background:rgba(90,158,111,.07);border-color:rgba(90,158,111,.28);color:#82c090}
.sofy-banner-warning{background:rgba(192,152,72,.07);border-color:rgba(192,152,72,.28);color:#c0a464}
.sofy-banner-danger{background:rgba(192,120,72,.07);border-color:rgba(192,120,72,.28);color:#c08464}
.sofy-banner-icon{flex-shrink:0;font-size:14px}
.sofy-banner-msg{flex:1}
.sofy-banner-action{margin-left:auto;flex-shrink:0}
.sofy-banner-close{background:none;border:none;color:inherit;cursor:pointer;font-size:17px;line-height:1;padding:0;opacity:.55;transition:opacity var(--t);flex-shrink:0}
.sofy-banner-close:hover{opacity:1}

/* ── copy button ─────────────────────────────────────────────────────────── */
.sofy-copy-btn.copied{color:var(--success)!important;border-color:var(--success)!important}

/* ── range input ─────────────────────────────────────────────────────────── */
.sofy-range-wrap{display:flex;align-items:center;gap:12px}
.sofy-form-range{flex:1;-webkit-appearance:none;appearance:none;height:6px;background:var(--surf2);border:1px solid var(--border);border-radius:100px;outline:none;cursor:pointer;transition:border-color var(--t)}
.sofy-form-range::-webkit-slider-thumb{-webkit-appearance:none;width:18px;height:18px;background:var(--accent);border-radius:50%;cursor:pointer;border:none;box-shadow:0 0 0 3px rgba(217,119,87,.15)}
.sofy-form-range::-moz-range-thumb{width:18px;height:18px;background:var(--accent);border-radius:50%;cursor:pointer;border:none}
.sofy-form-range:focus{border-color:var(--accent)}
.sofy-range-val{font-size:13px;color:var(--text);min-width:32px;text-align:right;font-variant-numeric:tabular-nums}

/* ── hamburger / mobile nav ──────────────────────────────────────────────── */
.sofy-hamburger{display:none;background:none;border:1px solid var(--border);border-radius:6px;color:var(--muted);cursor:pointer;padding:5px 9px;font-size:16px;line-height:1;transition:color var(--t),border-color var(--t);align-items:center;flex-shrink:0}
.sofy-hamburger:hover{color:var(--accent);border-color:var(--accent)}
@media(max-width:640px){
  .sofy-hamburger{display:flex}
  .sofy-nav{position:relative!important;overflow:visible}
  .sofy-nav-links{display:none!important;position:absolute;top:100%;left:0;right:0;flex-direction:column!important;gap:0!important;background:rgba(255,250,245,.98);border-bottom:1px solid var(--border);padding:6px 0;backdrop-filter:blur(8px);z-index:20}
  .sofy-nav.nav-open .sofy-nav-links{display:flex!important}
  .sofy-nav-link{padding:10px 20px!important;border-radius:0!important;border-bottom:1px solid var(--border)}
  .sofy-nav-link:last-child{border-bottom:none}
  .sofy-main{padding:20px 16px}
  .sofy-page-hdr{flex-direction:column;align-items:flex-start;gap:10px}
  .sofy-page-title{font-size:17px}
  .sofy-stat-val{font-size:22px}
}
[data-theme="dark"] .sofy-nav.nav-open .sofy-nav-links{background:rgba(26,22,19,.97)}

/* ── locale switcher ─────────────────────────────────────────────────── */
.sofy-locale-sw{display:flex;align-items:center;margin-left:4px;border:1px solid var(--border);border-radius:6px;overflow:hidden}
.sofy-locale-btn{font-size:10px;font-weight:700;letter-spacing:.04em;padding:4px 8px;color:var(--muted);text-decoration:none;transition:color var(--t),background var(--t);line-height:1}
.sofy-locale-btn:hover{color:var(--text);background:rgba(0,0,0,.04)}
.sofy-locale-btn.active{color:var(--accent);background:rgba(217,119,87,.12)}

/* ════════════════════════════════════════════════════════════════════════════
   transitions.dev — portable t-* transitions. Semantic :root tokens + verbatim
   snippets, each with its own prefers-reduced-motion guard. Sofy-specific
   sizing/colors are isolated in the clearly-marked "Sofy:" blocks.
   ════════════════════════════════════════════════════════════════════════════ */
:root{
    --resize-dur:300ms;--resize-ease:cubic-bezier(0.22,1,0.36,1);
    --digit-dur:500ms;--digit-distance:8px;--digit-stagger:70ms;--digit-blur:2px;--digit-ease:cubic-bezier(0.34,1.45,0.64,1);--digit-dir-x:0;--digit-dir-y:1;
    --text-swap-dur:150ms;--text-swap-translate-y:4px;--text-swap-blur:2px;--text-swap-ease:ease-in-out;
    --modal-open-dur:250ms;--modal-close-dur:150ms;--modal-scale:0.96;--modal-scale-close:0.96;--modal-ease:cubic-bezier(0.22,1,0.36,1);
    --panel-open-dur:400ms;--panel-close-dur:350ms;--panel-translate-y:100px;--panel-blur:2px;--panel-ease:cubic-bezier(0.22,1,0.36,1);
    --icon-swap-dur:200ms;--icon-swap-blur:2px;--icon-swap-start-scale:0.25;--icon-swap-ease:ease-in-out;
    --check-opacity-dur:550ms;--check-rotate-dur:550ms;--check-rotate-from:80deg;--check-bob-dur:450ms;--check-y-amount:40px;--check-blur-dur:500ms;--check-blur-from:10px;--check-path-dur:550ms;--check-path-delay:80ms;--check-ease-out:cubic-bezier(0.22,1,0.36,1);--check-ease-opacity:cubic-bezier(0.22,1,0.36,1);--check-ease-rotate:cubic-bezier(0.22,1,0.36,1);--check-ease-bob:cubic-bezier(0.34,1.35,0.64,1);--check-ease-path:cubic-bezier(0.22,1,0.36,1);
    --avatar-lift:-4px;--avatar-dur:320ms;--avatar-scale:1.05;--avatar-falloff:0.45;--avatar-ease-in:cubic-bezier(0.22,1,0.36,1);--avatar-ease-out:cubic-bezier(0.34,3.85,0.64,1);
    --shake-distance:6px;--shake-overshoot:4px;--shake-dur-a:80ms;--shake-dur-b:60ms;--shake-ease:cubic-bezier(0.22,1,0.36,1);--revert-hold:3000ms;--revert-dur:280ms
}

/* — Modal open / close — */
.t-modal{transform-origin:center;transform:scale(var(--modal-scale));opacity:0;pointer-events:none;transition:transform var(--modal-open-dur) var(--modal-ease),opacity var(--modal-open-dur) var(--modal-ease);will-change:transform,opacity}
.t-modal.is-open{transform:scale(1);opacity:1;pointer-events:auto}
.t-modal.is-closing{transform:scale(var(--modal-scale-close));opacity:0;pointer-events:none;transition:transform var(--modal-close-dur) var(--modal-ease),opacity var(--modal-close-dur) var(--modal-ease)}
@media (prefers-reduced-motion: reduce){.t-modal{transition:none !important}}

/* — Panel reveal — */
.t-panel-slide{transform:translateY(var(--panel-translate-y));opacity:0;filter:blur(var(--panel-blur));pointer-events:none;transition:transform var(--panel-close-dur) var(--panel-ease),opacity var(--panel-close-dur) var(--panel-ease),filter var(--panel-close-dur) var(--panel-ease);will-change:transform,opacity,filter}
.t-panel-slide[data-open="true"]{transform:translateY(0);opacity:1;filter:blur(0);pointer-events:auto;transition:transform var(--panel-open-dur) var(--panel-ease),opacity var(--panel-open-dur) var(--panel-ease),filter var(--panel-open-dur) var(--panel-ease)}
@media (prefers-reduced-motion: reduce){.t-panel-slide{transition:none !important}}

/* — Icon swap — */
.t-icon-swap{position:relative;display:inline-grid}
.t-icon-swap .t-icon{grid-area:1 / 1;transition:opacity var(--icon-swap-dur) var(--icon-swap-ease),filter var(--icon-swap-dur) var(--icon-swap-ease),transform var(--icon-swap-dur) var(--icon-swap-ease);will-change:opacity,filter,transform}
.t-icon-swap[data-state="a"] .t-icon[data-icon="a"],.t-icon-swap[data-state="b"] .t-icon[data-icon="b"]{opacity:1;filter:blur(0);transform:scale(1)}
.t-icon-swap[data-state="a"] .t-icon[data-icon="b"],.t-icon-swap[data-state="b"] .t-icon[data-icon="a"]{opacity:0;filter:blur(var(--icon-swap-blur));transform:scale(var(--icon-swap-start-scale))}
@media (prefers-reduced-motion: reduce){.t-icon-swap .t-icon{transition:none !important}}

/* — Number pop-in — */
@keyframes t-digit-pop-in{0%{transform:translate(calc(var(--digit-distance) * var(--digit-dir-x)),calc(var(--digit-distance) * var(--digit-dir-y)));opacity:0;filter:blur(var(--digit-blur))}100%{transform:translate(0,0);opacity:1;filter:blur(0)}}
.t-digit-group{display:inline-flex;align-items:baseline}
.t-digit{display:inline-block;will-change:transform,opacity,filter}
.t-digit-group.is-animating .t-digit{animation:t-digit-pop-in var(--digit-dur) var(--digit-ease) both}
.t-digit-group.is-animating .t-digit[data-stagger="1"]{animation-delay:var(--digit-stagger)}
.t-digit-group.is-animating .t-digit[data-stagger="2"]{animation-delay:calc(var(--digit-stagger) * 2)}
@media (prefers-reduced-motion: reduce){.t-digit-group .t-digit{animation:none !important}}

/* — Text states swap — */
.t-text-swap{display:inline-block;transform:translateY(0);filter:blur(0);opacity:1;transition:transform var(--text-swap-dur) var(--text-swap-ease),filter var(--text-swap-dur) var(--text-swap-ease),opacity var(--text-swap-dur) var(--text-swap-ease);will-change:transform,filter,opacity}
.t-text-swap.is-exit{transform:translateY(calc(var(--text-swap-translate-y) * -1));filter:blur(var(--text-swap-blur));opacity:0}
.t-text-swap.is-enter-start{transform:translateY(var(--text-swap-translate-y));filter:blur(var(--text-swap-blur));opacity:0;transition:none}
@media (prefers-reduced-motion: reduce){.t-text-swap{transition:none !important}}

/* — Success check — */
.t-success-check{display:inline-block;transform-origin:center;opacity:0;will-change:transform,opacity,filter}
.t-success-check svg{display:block;overflow:visible}
.t-success-check svg path{stroke-dasharray:20;stroke-dashoffset:20}
.t-success-check[data-state="in"]{animation:t-check-fade var(--check-opacity-dur) var(--check-ease-opacity) forwards,t-check-rotate var(--check-rotate-dur) var(--check-ease-rotate) forwards,t-check-blur var(--check-blur-dur) var(--check-ease-out) forwards,t-check-bob var(--check-bob-dur) var(--check-ease-bob) forwards}
.t-success-check[data-state="in"] svg path{animation:t-check-draw var(--check-path-dur) var(--check-ease-path) var(--check-path-delay,0ms) forwards}
@keyframes t-check-fade{from{opacity:0}to{opacity:1}}
@keyframes t-check-rotate{from{transform:rotate(var(--check-rotate-from))}to{transform:rotate(0deg)}}
@keyframes t-check-blur{from{filter:blur(var(--check-blur-from))}to{filter:blur(0)}}
@keyframes t-check-bob{from{translate:0 var(--check-y-amount)}to{translate:0 0}}
@keyframes t-check-draw{to{stroke-dashoffset:0}}
@media (prefers-reduced-motion: reduce){.t-success-check{animation:none !important;opacity:1}.t-success-check svg path{animation:none !important;stroke-dashoffset:0 !important}}
/* Sofy: success-check sizing + color (bring-your-own per the skill) */
.sofy-check svg{width:46px;height:46px}
.sofy-check svg path{stroke:var(--success);stroke-width:4;stroke-linecap:round;stroke-linejoin:round;fill:none}

/* — Avatar group hover — */
.t-avatar{transform-origin:center;transform:translateY(var(--shift,0px)) scale(var(--scale-active,1));transition:transform var(--avatar-dur) var(--avatar-ease-in);will-change:transform}
@media (prefers-reduced-motion: reduce){.t-avatar{transition:none !important;transform:none !important}}
/* Sofy: avatar/chip row layout */
.sofy-avatar-group{display:inline-flex;align-items:center}
.sofy-avatar-group .t-avatar{margin-left:-10px}
.sofy-avatar-group .t-avatar:first-child{margin-left:0}
.sofy-avatar-group .sofy-avatar{border:2px solid var(--bg);box-shadow:var(--shadow)}

/* — Error state shake — */
.t-input{transition:border-color 150ms ease-out;will-change:transform}
.t-input.is-error{transition:border-color var(--revert-dur,280ms) ease-out}
.t-error-msg{opacity:0;visibility:hidden;transition:opacity var(--revert-dur,280ms) ease-out,visibility 0s linear var(--revert-dur,280ms)}
.t-input-wrap.is-error .t-error-msg{opacity:1;visibility:visible;transition:opacity var(--revert-dur,280ms) ease-out,visibility 0s linear 0s}
.t-input.is-shaking{animation:t-input-shake calc(var(--shake-dur-a) * 2 + var(--shake-dur-b) * 2) linear}
@keyframes t-input-shake{0%{transform:translateX(0);animation-timing-function:var(--shake-ease)}28.57%{transform:translateX(var(--shake-distance));animation-timing-function:var(--shake-ease)}57.14%{transform:translateX(calc(var(--shake-distance) * -1));animation-timing-function:var(--shake-ease)}78.57%{transform:translateX(var(--shake-overshoot));animation-timing-function:var(--shake-ease)}100%{transform:translateX(0)}}
@media (prefers-reduced-motion: reduce){.t-input{animation:none !important;transform:none !important}}
/* Sofy: error border + message sit on the existing form classes */
.t-input.is-error.sofy-form-ctrl{border-color:var(--danger)}
CSS;
    }

    // ── JS (tabs only, ~5 lines) ──────────────────────────────────────────────

    private function getJs(): string
    {
        return <<<'JS'
<script>
/* ── Tabs ── */
document.querySelectorAll('.sofy-tab-btn').forEach(function(btn){
    btn.addEventListener('click',function(){
        var tabs=btn.closest('.sofy-tabs');
        tabs.querySelectorAll('.sofy-tab-btn,.sofy-tab-panel').forEach(function(el){el.classList.remove('active')});
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.add('active');
    });
});

/* ── Toast auto-dismiss ── */
document.querySelectorAll('.sofy-toast[data-dismiss]').forEach(function(t){
    var s=parseInt(t.dataset.dismiss,10);
    if(s>0){setTimeout(function(){t.style.opacity='0';t.style.transform='translateX(16px)';setTimeout(function(){t.remove();},320);},s*1000);}
});

/* ── Drawer ── */
var sofyDrawer={
    open:function(id){var d=document.getElementById(id);if(d)d.classList.add('open');},
    close:function(id){var d=document.getElementById(id);if(d)d.classList.remove('open');}
};
document.addEventListener('keydown',function(e){if(e.key==='Escape'){document.querySelectorAll('.sofy-drawer.open').forEach(function(d){d.classList.remove('open');});}});

/* ── DataTable ── */
function sofyDT(id){
    var wrap=document.getElementById(id);
    if(!wrap)return{search:function(){},sort:function(){}};
    if(!wrap._dt){
        wrap._dt=(function(el){
            var perPage=parseInt(el.dataset.perPage,10)||15;
            var state={q:'',col:-1,dir:1,page:1};
            var allRows=Array.from(el.querySelectorAll('tbody tr.sofy-dt-row'));
            function filtered(){
                return allRows.filter(function(r){return !state.q||r.innerText.toLowerCase().includes(state.q);});
            }
            function sorted(rows){
                if(state.col<0)return rows;
                return rows.slice().sort(function(a,b){
                    var ac=a.querySelectorAll('td')[state.col],bc=b.querySelectorAll('td')[state.col];
                    var av=ac?(ac.dataset.val||ac.textContent):'',bv=bc?(bc.dataset.val||bc.textContent):'';
                    var an=parseFloat(av),bn=parseFloat(bv);
                    return(!isNaN(an)&&!isNaN(bn)?(an-bn):av.localeCompare(bv))*state.dir;
                });
            }
            function render(){
                var f=sorted(filtered()),total=f.length;
                var start=(state.page-1)*perPage;
                var vis=perPage>0?f.slice(start,start+perPage):f;
                allRows.forEach(function(r){r.classList.add('hidden');});
                vis.forEach(function(r){r.classList.remove('hidden');});
                var info=document.getElementById(id+'-info');
                if(info){var end=perPage>0?Math.min(start+perPage,total):total;info.textContent=total>0?'Showing '+(start+1)+'–'+end+' of '+total:'No results';}
                var pager=document.getElementById(id+'-pager');
                if(pager&&perPage>0){
                    var pages=Math.ceil(total/perPage),h='';
                    for(var p=1;p<=pages;p++){h+='<a href="#" class="sofy-page-btn'+(p===state.page?' active':'')+'" data-p="'+p+'">'+p+'</a>';}
                    pager.innerHTML=h;
                    pager.querySelectorAll('[data-p]').forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();state.page=parseInt(this.dataset.p,10);render();});});
                }
                el.querySelectorAll('.sofy-dt-th').forEach(function(th){th.removeAttribute('data-sort');if(parseInt(th.dataset.col,10)===state.col)th.setAttribute('data-sort',state.dir===1?'asc':'desc');});
            }
            render();
            return{search:function(q){state.q=q.toLowerCase();state.page=1;render();},sort:function(c){if(state.col===c)state.dir*=-1;else{state.col=c;state.dir=1;}render();}};
        })(wrap);
    }
    return wrap._dt;
}
document.querySelectorAll('.sofy-dt').forEach(function(el){if(el.id)sofyDT(el.id);});

/* ── Theme toggle ── */
function sofyToggleTheme(){
    var html=document.documentElement;
    var next=html.getAttribute('data-theme')==='light'?'dark':'light';
    html.setAttribute('data-theme',next);
    localStorage.setItem('sofy-theme',next);
    document.querySelectorAll('.sofy-theme-btn .t-icon-swap').forEach(function(s){s.setAttribute('data-state',next==='dark'?'b':'a');});
}

/* ── Copy to clipboard ── */
function sofyCopy(btn){
    var txt=btn.dataset.copy;if(!txt)return;
    var swap=btn.querySelector('.t-text-swap')||btn;
    if(btn.dataset.label===undefined)btn.dataset.label=swap.textContent;
    var orig=btn.dataset.label;
    var done=function(){btn.classList.add('copied');sofySwapText(swap,btn.dataset.done||'Copied!');setTimeout(function(){btn.classList.remove('copied');sofySwapText(swap,orig);},2000);};
    if(navigator.clipboard){navigator.clipboard.writeText(txt).then(done).catch(done);}
    else{var ta=document.createElement('textarea');ta.value=txt;ta.style.cssText='position:fixed;opacity:0';document.body.appendChild(ta);ta.select();try{document.execCommand('copy');}catch(e){}document.body.removeChild(ta);done();}
}

/* ── transitions.dev orchestration ── */
function sofySwapText(el,next){
    if(!el)return;
    var dur=parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--text-swap-dur'))||150;
    el.classList.add('is-exit');
    setTimeout(function(){
        el.textContent=next;
        el.classList.remove('is-exit');
        el.classList.add('is-enter-start');
        void el.offsetHeight;
        el.classList.remove('is-enter-start');
    },dur);
}

var sofyModal={
    open:function(id){
        var d=document.getElementById(id);if(!d)return;
        var m=d.querySelector('.t-modal');
        if(d.showModal&&!d.open)d.showModal();
        if(m){m.classList.remove('is-closing');void m.offsetWidth;m.classList.add('is-open');}
        if(!d._sofyBound){
            d._sofyBound=1;
            d.addEventListener('cancel',function(e){e.preventDefault();sofyModal.close(id);});
            d.addEventListener('click',function(e){if(e.target===d)sofyModal.close(id);});
        }
    },
    close:function(id){
        var d=document.getElementById(id);if(!d)return;
        var m=d.querySelector('.t-modal');
        var ms=parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--modal-close-dur'))||150;
        if(m){m.classList.remove('is-open');m.classList.add('is-closing');}
        setTimeout(function(){if(m)m.classList.remove('is-closing');if(d.open)d.close();},ms);
    }
};

/* Error-state shake — for server-rendered validation errors (persistent, clears on input). */
function sofyFormError(wrap){
    var input=wrap.querySelector('.t-input');
    if(!input)return;
    wrap.classList.add('is-error');input.classList.add('is-error');
    input.classList.remove('is-shaking');void input.offsetWidth;input.classList.add('is-shaking');
    var cs=getComputedStyle(document.documentElement);
    var a=parseFloat(cs.getPropertyValue('--shake-dur-a'))||80,b=parseFloat(cs.getPropertyValue('--shake-dur-b'))||60;
    setTimeout(function(){input.classList.remove('is-shaking');},a*2+b*2+20);
    var field=wrap.querySelector('input,textarea,select');
    if(field)field.addEventListener('input',function clr(){wrap.classList.remove('is-error');input.classList.remove('is-error');field.removeEventListener('input',clr);});
}

/* Distance-falloff hover spring on a horizontal row (avatars, chips). */
function sofyAvatarGroup(root){
    var avatars=Array.prototype.slice.call(root.querySelectorAll('.t-avatar'));
    var cs=getComputedStyle(document.documentElement);
    var num=function(n,fb){var v=parseFloat(cs.getPropertyValue(n));return isFinite(v)?v:fb;};
    var ease=function(n,fb){return cs.getPropertyValue(n).trim()||fb;};
    function setShifts(active,phase){
        var lift=num('--avatar-lift',-4),falloff=num('--avatar-falloff',0.45),scale=num('--avatar-scale',1.05);
        var tf=phase==='out'?ease('--avatar-ease-out','cubic-bezier(0.34,3.85,0.64,1)'):ease('--avatar-ease-in','cubic-bezier(0.22,1,0.36,1)');
        avatars.forEach(function(el,i){
            el.style.transitionTimingFunction=tf;
            if(active==null){el.style.setProperty('--shift','0px');el.style.setProperty('--scale-active','1');return;}
            var d=Math.abs(i-active);
            el.style.setProperty('--shift',(lift*Math.pow(falloff,d)).toFixed(3)+'px');
            el.style.setProperty('--scale-active',i===active?String(scale):'1');
        });
    }
    avatars.forEach(function(el,i){el.addEventListener('mouseenter',function(){setShifts(i,'in');});});
    root.addEventListener('mouseleave',function(){setShifts(null,'out');});
}

/* Success check — measure path length once, then (re)play the appear. */
function sofyShowCheck(el){
    if(!el)return;
    var path=el.querySelector('svg path');
    if(path&&!el._measured){el._measured=1;var len=Math.ceil(path.getTotalLength());path.style.strokeDasharray=String(len);path.style.strokeDashoffset=String(len);}
    el.setAttribute('data-state','out');void el.offsetWidth;el.setAttribute('data-state','in');
}

/* Number pop-in — replace digits and replay (entrance plays on load via .is-animating). */
function sofyDigits(group,str){
    if(!group)return;
    group.classList.remove('is-animating');
    group.textContent='';
    var chars=String(str).split('');
    chars.forEach(function(ch,i){var s=document.createElement('span');s.className='t-digit';s.textContent=ch;if(i===chars.length-2)s.dataset.stagger='1';else if(i===chars.length-1)s.dataset.stagger='2';group.appendChild(s);});
    void group.offsetHeight;group.classList.add('is-animating');
}

document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('.t-digit-group').forEach(function(g){g.classList.add('is-animating');});
    var th=document.documentElement.getAttribute('data-theme')==='dark'?'b':'a';
    document.querySelectorAll('.sofy-theme-btn .t-icon-swap').forEach(function(s){s.setAttribute('data-state',th);});
    document.querySelectorAll('.t-avatar-group').forEach(sofyAvatarGroup);
    document.querySelectorAll('.t-input-wrap[data-error="1"]').forEach(sofyFormError);
    document.querySelectorAll('.t-success-check[data-autoplay]').forEach(sofyShowCheck);
});

/* ── Mobile nav ── */
document.querySelectorAll('.sofy-nav-link').forEach(function(a){
    a.addEventListener('click',function(){var n=a.closest('.sofy-nav');if(n)n.classList.remove('nav-open');});
});
document.addEventListener('click',function(e){
    if(!e.target.closest('.sofy-nav'))document.querySelectorAll('.sofy-nav.nav-open').forEach(function(n){n.classList.remove('nav-open');});
});

/* ── Command Palette ── */
var sofyCmd=(function(){
    var el=document.getElementById('sofy-cmd');
    if(!el)return{open:function(){},close:function(){},toggle:function(){},filter:function(){}};
    var input=document.getElementById('sofy-cmd-input');
    var list=document.getElementById('sofy-cmd-list');
    function open(){el.hidden=false;if(input){input.value='';input.focus();}filter('');}
    function close(){el.hidden=true;}
    function filter(q){
        q=q.toLowerCase();
        if(!list)return;
        list.querySelectorAll('.sofy-cmd-item').forEach(function(it){it.classList.toggle('hidden',!!(q&&!it.dataset.search.includes(q)));});
        list.querySelectorAll('.sofy-cmd-group').forEach(function(g){g.style.display=Array.from(g.querySelectorAll('.sofy-cmd-item')).some(function(it){return!it.classList.contains('hidden');})?'':'none';});
    }
    document.addEventListener('keydown',function(e){
        if((e.metaKey||e.ctrlKey)&&e.key==='k'){e.preventDefault();el.hidden?open():close();}
        if(e.key==='Escape'&&!el.hidden)close();
    });
    return{open:open,close:close,toggle:function(){el.hidden?open():close();},filter:filter};
})();
</script>
JS;
    }
}
