<?php

declare(strict_types=1);

namespace Sofy\Admin\Controllers;

use Sofy\Admin\Admin;
use Sofy\Cache\Cache;
use Sofy\Core\Application;
use Sofy\Http\Request;
use Sofy\Http\Response;
use Sofy\View\Icons;
use Sofy\View\UI;

/**
 * Admin → Updates page. One-click framework upgrade backed by the same
 * `php sofy update` plumbing the CLI uses, plus a release-notes feed
 * pulled from GitHub releases (cached) with a local CHANGELOG.md fallback.
 *
 * Routes:
 *   GET  /admin/system/update      status page + release log
 *   POST /admin/system/update/run  execute `php sofy update --no-migrate`
 *   POST /admin/system/update/refresh-notes  invalidate release-notes cache
 */
class UpdateController
{
    private const string GITHUB_RELEASES_URL = 'https://api.github.com/repos/sofyphp/framework/releases?per_page=20';
    private const string PACKAGIST_URL       = 'https://repo.packagist.org/p2/sofyphp/framework.json';
    private const string CACHE_KEY_NOTES     = 'sofy.updates.release_notes';
    private const string CACHE_KEY_PACKAGIST = 'sofy.updates.packagist';
    private const int    CACHE_TTL           = 1800; // 30 min

    public function index(): Response
    {
        $current   = ltrim(Application::version(), 'v');
        $latest    = $this->latestStable();
        $isLatest  = $latest === null ? null : version_compare($current, $latest, '>=');
        $releases  = $this->releaseNotes();

        // ── Status banner ───────────────────────────────────────────────────
        $banner = match (true) {
            $latest === null => UI::alert(
                'Could not reach Packagist or GitHub to check for updates. The button still works — it will retry on click.',
                'warning',
                'Offline check',
            ),
            $isLatest === true => UI::alert(
                "You're on the latest stable release (v{$current}).",
                'success',
                'Up to date',
            ),
            default => UI::alert(
                "A newer release is available: v{$latest}. You are on v{$current}.",
                'info',
                'Update available',
            ),
        };

        // ── Version stat tiles ──────────────────────────────────────────────
        $stats = UI::grid(4, [
            UI::stat('Installed', 'v' . $current,                description: 'this server'),
            UI::stat('Latest',    $latest === null ? '—' : 'v' . $latest, description: 'stable on Packagist'),
            UI::stat('Releases',  (string) count($releases),     description: 'from GitHub'),
            UI::stat('PHP',       PHP_VERSION,                   description: PHP_SAPI),
        ]);

        // ── Update action card ──────────────────────────────────────────────
        $csrf = function_exists('csrf_token') ? csrf_token() : '';
        $btnLabel = $isLatest === true ? 'Re-apply current release' : 'Update now';
        $confirm  = $isLatest === true
            ? 'This will re-download and overwrite framework files with the same version. Continue?'
            : "This will upgrade Sofy to v{$latest} by overwriting src/, bootstrap/ and the sofy CLI. Continue?";

        $actionForm = UI::raw(
            '<form method="POST" action="/admin/system/update/run" '
            . 'onsubmit="return confirm(' . htmlspecialchars(json_encode($confirm), ENT_QUOTES, 'UTF-8') . ');" '
            . 'style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">'
            . ($csrf !== '' ? '<input type="hidden" name="_token" value="' . htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') . '">' : '')
            . '<button type="submit" class="sofy-btn sofy-btn-primary">' . UI::icon('download', size: 14) . ' ' . htmlspecialchars($btnLabel, ENT_QUOTES, 'UTF-8') . '</button>'
            . '<span class="sofy-form-hint">Runs <code>php sofy update --no-migrate</code> on this server. The request may block 30–120s while files download.</span>'
            . '</form>',
        );

        $warning = UI::alert(
            UI::raw(
                'The web user must have write access to <code class="sofy-docs-code">src/</code>, '
                . '<code class="sofy-docs-code">bootstrap/</code> and <code class="sofy-docs-code">sofy</code>, '
                . 'plus outbound HTTPS to Packagist + GitHub. If permissions are wrong, run '
                . '<code class="sofy-docs-code">php sofy update</code> from a shell instead.',
            ),
            'warning',
            'Heads up',
        );

        // ── Release notes feed ──────────────────────────────────────────────
        $refreshForm = UI::raw(
            '<form method="POST" action="/admin/system/update/refresh-notes" style="display:inline">'
            . ($csrf !== '' ? '<input type="hidden" name="_token" value="' . htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') . '">' : '')
            . '<button type="submit" class="sofy-btn sofy-btn-ghost">' . UI::icon('refresh', size: 13) . ' Refresh release notes</button>'
            . '</form>',
        );

        $notesBody = empty($releases)
            ? UI::emptyState(
                'No release notes',
                'Release notes are pulled from GitHub Releases at sofyphp/framework. Push a tag with a release description on GitHub — it will appear here on next refresh (cached 30 min).',
                icon: '◯',
            )
            : UI::raw($this->renderReleaseList($releases, $current));

        return Admin::page('Updates')
            ->header('Updates', $refreshForm)
            ->add(
                $banner,
                $stats,
                UI::card('Apply update', UI::raw((string) $actionForm . (string) $warning)),
                UI::card('Release notes', $notesBody),
                UI::raw($this->styles()),
            )
            ->response();
    }

    public function run(Request $request): Response
    {
        $base  = $this->basePath();
        $sofy  = $base . '/sofy';
        $php   = PHP_BINARY ?: 'php';
        $cmd   = escapeshellarg($php) . ' ' . escapeshellarg($sofy) . ' update --no-migrate 2>&1';

        // Pipe stdin = /dev/null and answer the interactive confirm with "yes"
        // — the CLI prompt asks before applying, and we already confirmed in JS.
        $cmd = 'echo y | ' . $cmd;

        $start  = microtime(true);
        $output = (string) shell_exec($cmd);
        $took   = number_format(microtime(true) - $start, 2);

        // After the update, Application::version() reads the freshly-written
        // composer.json — refresh it to show the new version.
        $newVer = ltrim($this->readComposerVersion(), 'v');

        // Bust caches so the next /admin/system/update reflects reality.
        Cache::forget(self::CACHE_KEY_PACKAGIST);
        Cache::forget(self::CACHE_KEY_NOTES);

        $resultPane = UI::card(
            'Output (' . $took . 's)',
            UI::raw(
                '<pre class="sofy-update-log">'
                . $this->ansiToHtml($output)
                . '</pre>',
            ),
        );

        return Admin::page('Update — done')
            ->header('Update — done', UI::button('← Back', '/admin/system/update', 'ghost'))
            ->add(
                UI::alert(
                    "Now running v{$newVer}. Restart PHP-FPM / opcache reset may be needed for the changes to take effect.",
                    'success',
                    'Update completed',
                ),
                $resultPane,
                UI::raw($this->styles()),
            )
            ->response();
    }

    public function refreshNotes(): Response
    {
        Cache::forget(self::CACHE_KEY_NOTES);
        Cache::forget(self::CACHE_KEY_PACKAGIST);
        return Response::redirect('/admin/system/update');
    }

    // ── Data: latest version + release notes ────────────────────────────────

    private function latestStable(): ?string
    {
        return Cache::remember(self::CACHE_KEY_PACKAGIST, self::CACHE_TTL, function (): ?string {
            $body = $this->httpGet(self::PACKAGIST_URL);
            if ($body === null) return null;
            $data = json_decode($body, true);
            $pkgs = $data['packages']['sofyphp/framework'] ?? [];
            $names = [];
            foreach ($pkgs as $entry) {
                $v = (string) ($entry['version'] ?? '');
                if ($v === '' || str_starts_with($v, 'dev-')) continue;
                $names[] = ltrim($v, 'v');
            }
            if ($names === []) return null;
            usort($names, static fn(string $a, string $b): int => version_compare($b, $a));
            return $names[0];
        });
    }

    /** @return list<array{tag:string, name:string, body:string, published_at:?string, url:?string}> */
    private function releaseNotes(): array
    {
        return Cache::remember(self::CACHE_KEY_NOTES, self::CACHE_TTL, function (): array {
            $body = $this->httpGet(self::GITHUB_RELEASES_URL, ['Accept: application/vnd.github+json']);
            if ($body === null) {
                return $this->parseLocalChangelog();
            }
            $data = json_decode($body, true);
            $out  = [];
            if (is_array($data)) {
                foreach ($data as $r) {
                    if (!is_array($r) || !empty($r['draft'])) continue;
                    $out[] = [
                        'tag'          => (string) ($r['tag_name']     ?? ''),
                        'name'         => (string) ($r['name']         ?? ($r['tag_name'] ?? '')),
                        'body'         => (string) ($r['body']         ?? ''),
                        'published_at' => isset($r['published_at']) ? (string) $r['published_at'] : null,
                        'url'          => isset($r['html_url'])     ? (string) $r['html_url']     : null,
                    ];
                }
            }
            // GitHub Releases is opt-in (tags ≠ releases). If the maintainer
            // hasn't published any, fall through to a local CHANGELOG.md so
            // the admin still has a story to tell.
            return $out !== [] ? $out : $this->parseLocalChangelog();
        }) ?? [];
    }

    /** @return list<array{tag:string, name:string, body:string, published_at:?string, url:?string}> */
    private function parseLocalChangelog(): array
    {
        $path = $this->basePath() . '/CHANGELOG.md';
        if (!is_file($path)) return [];

        $content = (string) file_get_contents($path);
        // Split on H2 headings like "## v0.4.2 — 2026-05-29" or "## 0.4.2"
        $parts = preg_split('/^##\s+/m', $content, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts)) return [];

        $out = [];
        foreach ($parts as $section) {
            $section = trim($section);
            if ($section === '' || stripos($section, 'changelog') === 0) continue;
            [$head, $body] = array_pad(explode("\n", $section, 2), 2, '');
            if (!preg_match('/v?(\d+\.\d+\.\d+(?:[-+][\w.]+)?)/', $head, $m)) continue;
            $out[] = [
                'tag'          => 'v' . $m[1],
                'name'         => trim($head),
                'body'         => trim((string) $body),
                'published_at' => null,
                'url'          => null,
            ];
        }
        return $out;
    }

    // ── Rendering ────────────────────────────────────────────────────────────

    /** @param list<array{tag:string, name:string, body:string, published_at:?string, url:?string}> $releases */
    private function renderReleaseList(array $releases, string $currentVersion): string
    {
        $html = '<div class="sofy-release-list">';
        foreach ($releases as $r) {
            $tag       = $r['tag'];
            $normTag   = ltrim($tag, 'v');
            $isCurrent = $normTag === $currentVersion;
            $isNewer   = $currentVersion !== '' && version_compare($normTag, $currentVersion, '>');

            $badge = match (true) {
                $isCurrent => '<span class="sofy-release-badge sofy-release-badge-current">' . UI::icon('check-circle', size: 12) . ' installed</span>',
                $isNewer   => '<span class="sofy-release-badge sofy-release-badge-new">' . UI::icon('arrow-up', size: 12) . ' newer</span>',
                default    => '<span class="sofy-release-badge sofy-release-badge-old">' . UI::icon('clock', size: 12) . ' older</span>',
            };

            $date = $r['published_at']
                ? '<time>' . htmlspecialchars(substr($r['published_at'], 0, 10), ENT_QUOTES, 'UTF-8') . '</time>'
                : '';

            $link = $r['url']
                ? '<a class="sofy-docs-a" target="_blank" rel="noopener" href="' . htmlspecialchars($r['url'], ENT_QUOTES, 'UTF-8') . '">GitHub →</a>'
                : '';

            $bodyHtml = $r['body'] !== ''
                ? $this->renderMarkdown($r['body'])
                : '<p class="sofy-release-empty">No description for this release.</p>';

            $html .= '<article class="sofy-release' . ($isCurrent ? ' sofy-release-current' : '') . '">'
                . '<header class="sofy-release-head">'
                . '<h3>' . htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') . '</h3>'
                . $badge
                . '<span class="sofy-release-meta">' . $date . ' ' . $link . '</span>'
                . '</header>'
                . '<div class="sofy-release-body">' . $bodyHtml . '</div>'
                . '</article>';
        }
        $html .= '</div>';
        return $html;
    }

    /** Tiny markdown subset — enough for release notes (headings, lists, code, bold/italic, links). */
    private function renderMarkdown(string $md): string
    {
        $md = str_replace(["\r\n", "\r"], "\n", $md);

        // Escape first; we re-inject controlled HTML below.
        $esc = htmlspecialchars($md, ENT_QUOTES, 'UTF-8');

        // Fenced code blocks ```…```
        $esc = preg_replace_callback('/```(\w*)\n(.*?)```/s', static function (array $m): string {
            return '<pre class="sofy-md-code"><code>' . $m[2] . '</code></pre>';
        }, $esc) ?? $esc;

        // Inline `code`
        $esc = preg_replace('/`([^`]+)`/', '<code class="sofy-docs-code">$1</code>', $esc) ?? $esc;

        // Headings (H3..H4 — release notes already live under "Release notes")
        $esc = preg_replace('/^####\s+(.+)$/m', '<h5>$1</h5>', $esc) ?? $esc;
        $esc = preg_replace('/^###\s+(.+)$/m',  '<h4>$1</h4>', $esc) ?? $esc;
        $esc = preg_replace('/^##\s+(.+)$/m',   '<h4>$1</h4>', $esc) ?? $esc;

        // Bold + italic
        $esc = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $esc) ?? $esc;
        $esc = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $esc) ?? $esc;

        // Links [label](url) — url is already escaped, accept only http(s)
        $esc = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', static function (array $m): string {
            $url = $m[2];
            if (!preg_match('#^https?://#i', html_entity_decode($url, ENT_QUOTES, 'UTF-8'))) {
                return $m[0];
            }
            return '<a class="sofy-docs-a" target="_blank" rel="noopener" href="' . $url . '">' . $m[1] . '</a>';
        }, $esc) ?? $esc;

        // Bullet lists
        $esc = preg_replace_callback('/((?:^[\-\*]\s+.+\n?)+)/m', static function (array $m): string {
            $items = preg_split('/\n/', trim($m[1])) ?: [];
            $li    = '';
            foreach ($items as $line) {
                $li .= '<li>' . preg_replace('/^[\-\*]\s+/', '', $line) . '</li>';
            }
            return '<ul class="sofy-md-ul">' . $li . '</ul>';
        }, $esc) ?? $esc;

        // Paragraphs: split on blank lines.
        $blocks = preg_split('/\n{2,}/', $esc) ?: [];
        $html   = '';
        foreach ($blocks as $b) {
            $b = trim($b);
            if ($b === '') continue;
            if (preg_match('/^<(h\d|ul|pre|p)/i', $b)) {
                $html .= $b;
            } else {
                $html .= '<p>' . str_replace("\n", '<br>', $b) . '</p>';
            }
        }
        return $html;
    }

    private function ansiToHtml(string $output): string
    {
        $escaped = htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
        // Strip raw ANSI codes — the CLI uses them; we keep the log readable.
        return (string) preg_replace('/\x1b\[[0-9;]*m/', '', $escaped);
    }

    private function styles(): string
    {
        return <<<CSS
        <style>
            .sofy-release-list{display:flex;flex-direction:column;gap:14px}
            .sofy-release{border:1px solid var(--border);border-radius:10px;padding:14px 16px;background:var(--surface)}
            .sofy-release-current{border-color:var(--accent);box-shadow:0 0 0 1px var(--accent) inset}
            .sofy-release-head{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px}
            .sofy-release-head h3{margin:0;font-size:15px;font-family:var(--mono)}
            .sofy-release-meta{margin-left:auto;font-size:12px;color:var(--muted);display:inline-flex;gap:10px;align-items:center}
            .sofy-release-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;font-size:11px;border-radius:999px;font-weight:600}
            .sofy-release-badge-current{background:rgba(34,197,94,.12);color:#15803d}
            .sofy-release-badge-new{background:rgba(255,107,90,.14);color:var(--accent)}
            .sofy-release-badge-old{background:rgba(148,163,184,.18);color:#475569}
            .sofy-release-body{font-size:13.5px;line-height:1.55}
            .sofy-release-body h4,.sofy-release-body h5{margin:10px 0 4px;font-size:13px}
            .sofy-release-body p{margin:6px 0}
            .sofy-release-body ul{margin:6px 0 6px 18px}
            .sofy-release-empty{color:var(--muted);font-style:italic}
            .sofy-md-code{background:var(--surface-2,#f5f1ea);border:1px solid var(--border);border-radius:6px;padding:8px 10px;font-family:var(--mono);font-size:12.5px;overflow:auto}
            .sofy-md-ul li{margin:2px 0}
            .sofy-update-log{background:#1a1a1a;color:#e8e8e8;border-radius:8px;padding:14px;font-family:var(--mono);font-size:12.5px;max-height:480px;overflow:auto;white-space:pre-wrap}
        </style>
        CSS;
    }

    // ── HTTP + helpers ───────────────────────────────────────────────────────

    /** @param list<string> $headers */
    private function httpGet(string $url, array $headers = []): ?string
    {
        $ch = curl_init($url);
        if ($ch === false) return null;
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'Sofy-Admin/' . Application::version(),
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code !== 200) return null;
        return (string) $body;
    }

    private function basePath(): string
    {
        return function_exists('base_path') ? base_path() : (string) (getcwd() ?: '.');
    }

    private function readComposerVersion(): string
    {
        $path = $this->basePath() . '/composer.json';
        if (!is_file($path)) return Application::version();
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) && isset($data['version']) ? (string) $data['version'] : Application::version();
    }
}
