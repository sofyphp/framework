<?php

declare(strict_types=1);

namespace Sofy\Core;

use Throwable;

/**
 * Renders a rich HTML debug page for uncaught exceptions.
 * Only shown when APP_DEBUG=true.
 */
class ExceptionHandler
{
    private const CTX = 8; // lines of context around error line

    public function render(Throwable $e): string
    {
        $class   = get_class($e);
        $parts   = explode('\\', $class);
        $short   = (string) array_pop($parts);
        $ns      = $parts ? implode('\\', $parts) . '\\' : '';
        $msg     = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE);
        $file    = $e->getFile();
        $line    = $e->getLine();
        $relFile = $this->rel($file);

        $snippet = $this->snippet($file, $line);
        $trace   = $this->traceHtml($e->getTrace());
        $request = $this->requestHtml();
        $css     = $this->css();

        $phpVer = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $mem    = number_format(memory_get_usage(true) / 1024) . 'kb';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{$short}</title>
            <style>{$css}</style>
        </head>
        <body>
        <header>
            <div class="h-inner">
                <div class="h-left">
                    <div class="h-brand">So<span>fy</span></div>
                    <div class="exc-class"><span class="ns">{$ns}</span>{$short}</div>
                    <div class="exc-msg">{$msg}</div>
                    <div class="exc-loc">{$relFile}<span class="colon">:</span><span class="lineno">{$line}</span></div>
                </div>
                <div class="h-meta">
                    <div class="meta-row"><span class="meta-k">PHP</span><span class="meta-v">{$phpVer}</span></div>
                    <div class="meta-row"><span class="meta-k">MEM</span><span class="meta-v">{$mem}</span></div>
                    <div class="meta-row"><span class="meta-k">ENV</span><span class="meta-v">debug</span></div>
                </div>
            </div>
        </header>
        <main>
            <section>
                <h2>Source</h2>
                {$snippet}
            </section>
            <section>
                <h2>Stack Trace</h2>
                {$trace}
            </section>
            <section>
                <h2>Request</h2>
                {$request}
            </section>
        </main>
        </body>
        </html>
        HTML;
    }

    // ── Code snippet ──────────────────────────────────────────────────────────

    private function snippet(string $file, int $errorLine, bool $compact = false): string
    {
        if (!is_readable($file)) {
            return '<p class="muted">File not readable.</p>';
        }

        $all = file($file, FILE_IGNORE_NEW_LINES);
        if ($all === false) {
            return '<p class="muted">Could not read file.</p>';
        }

        $ctx  = $compact ? 4 : self::CTX;
        $from = max(0, $errorLine - $ctx - 1);
        $to   = min(count($all) - 1, $errorLine + $ctx - 1);

        $html = '<div class="snippet">';
        for ($i = $from; $i <= $to; $i++) {
            $num    = $i + 1;
            $active = $num === $errorLine ? ' hl' : '';
            $code   = htmlspecialchars($all[$i] ?? '', ENT_QUOTES | ENT_SUBSTITUTE);
            $html  .= "<div class=\"ln{$active}\"><span class=\"n\">{$num}</span>"
                    . "<span class=\"c\">{$code}</span></div>";
        }

        return $html . '</div>';
    }

    // ── Stack trace ───────────────────────────────────────────────────────────

    private function traceHtml(array $frames): string
    {
        if (empty($frames)) {
            return '<p class="muted">No trace available.</p>';
        }

        $html = '<div class="trace">';

        foreach ($frames as $i => $f) {
            $file = $f['file'] ?? null;
            $ln   = (int) ($f['line'] ?? 0);
            $fn   = htmlspecialchars(
                ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? ''),
                ENT_QUOTES | ENT_SUBSTITUTE
            );
            $loc  = $file ? $this->rel($file) . ':' . $ln : '[internal]';

            $body = ($file && $ln)
                ? '<div class="frame-body">' . $this->snippet($file, $ln, true) . '</div>'
                : '';

            $open = $i === 0 ? ' open' : '';
            $num  = $i + 1;

            $html .= <<<FRAME
            <details class="frame"{$open}>
                <summary>
                    <span class="f-n">#{$num}</span>
                    <span class="f-fn">{$fn}</span>
                    <span class="f-loc">{$loc}</span>
                </summary>
                {$body}
            </details>
            FRAME;
        }

        return $html . '</div>';
    }

    // ── Request info ──────────────────────────────────────────────────────────

    /**
     * Keys whose values must never appear on the debug page. Matches are
     * case-insensitive and substring-based — `password_confirmation` is
     * covered by `password`, `X-CSRF-Token` by `token`, etc.
     */
    private const array SENSITIVE_KEYS = [
        'password', 'passwd', 'pwd',
        'secret', 'token', '_token',
        'authorization', 'auth', 'api_key', 'apikey',
        'cookie',
        'remember_token', 'session', 'sessid',
        'card', 'cvv', 'cvc',
    ];

    private function requestHtml(): string
    {
        $method = htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? 'CLI', ENT_QUOTES);
        $uri    = htmlspecialchars($_SERVER['REQUEST_URI']    ?? '–',   ENT_QUOTES);

        $html = "<div class=\"req-line\"><span class=\"method\">{$method}</span>"
              . "<span class=\"uri\">{$uri}</span></div>";

        $groups = [
            'GET'     => $this->scrub($_GET),
            'POST'    => $this->scrub($_POST),
            'Headers' => $this->scrub($this->headers()),
            'Server'  => array_intersect_key($_SERVER, array_flip([
                'SERVER_NAME', 'SERVER_PORT', 'REMOTE_ADDR',
                'HTTP_HOST', 'HTTPS', 'DOCUMENT_ROOT', 'PHP_SELF',
            ])),
        ];

        foreach ($groups as $label => $data) {
            if (empty($data)) {
                continue;
            }
            $html .= "<details class=\"req-group\"><summary>{$label}</summary>"
                   . '<table class="kv">';
            foreach ($data as $k => $v) {
                $k     = htmlspecialchars((string) $k, ENT_QUOTES);
                $v     = htmlspecialchars(
                    is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v,
                    ENT_QUOTES
                );
                $html .= "<tr><td class=\"k\">{$k}</td><td class=\"v\">{$v}</td></tr>";
            }
            $html .= '</table></details>';
        }

        return $html;
    }

    /**
     * Replace sensitive values with `[redacted]` so the debug page never
     * leaks passwords, API tokens, session cookies or CSRF tokens — even
     * when an exception happens mid-login and the operator screenshots
     * the page into a bug tracker.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function scrub(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $needle = strtolower((string) $k);
            $hit = false;
            foreach (self::SENSITIVE_KEYS as $marker) {
                if (str_contains($needle, $marker)) { $hit = true; break; }
            }
            $out[$k] = $hit ? '[redacted]' : $v;
        }
        return $out;
    }

    private function headers(): array
    {
        $out = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name      = ucwords(strtolower(str_replace('_', '-', substr($k, 5))), '-');
                $out[$name] = $v;
            }
        }
        return $out;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function rel(string $path): string
    {
        $base = function_exists('base_path') ? base_path() : dirname(__DIR__, 2);
        return str_starts_with($path, $base)
            ? ltrim(substr($path, strlen($base)), DIRECTORY_SEPARATOR)
            : $path;
    }

    // ── CSS ───────────────────────────────────────────────────────────────────

    private function css(): string
    {
        return <<<'CSS'
        *,::before,::after{box-sizing:border-box;margin:0;padding:0}

        :root{
            --bg:       #080c14;
            --surf:     #0d1220;
            --border:   #1a2236;
            --text:     #d8e2f0;
            --muted:    #4a5570;
            --bright:   #e8f0fc;
            --accent:   #7c9bbf;
            --err:      #c07848;
            --err-dim:  rgba(192,120,72,.13);
            --err-num:  #d4895a;
            --green:    #3d8f5f;
            --mono:     'SF Mono','Fira Code','Cascadia Code',Menlo,monospace;
        }

        html,body{
            min-height:100%;
            background:var(--bg);
            color:var(--text);
            font:14px/1.6 system-ui,-apple-system,sans-serif;
        }

        /* stars */
        body::before{
            content:'';
            position:fixed;
            inset:0;
            background-image:
                radial-gradient(1px 1px at  6% 14%,#fff 0%,transparent 100%),
                radial-gradient(1px 1px at 19% 52%,#fff 0%,transparent 100%),
                radial-gradient(1px 1px at 33% 28%,#c8d4f0 0%,transparent 100%),
                radial-gradient(1px 1px at 47%  9%,#fff 0%,transparent 100%),
                radial-gradient(1px 1px at 61% 43%,#fff 0%,transparent 100%),
                radial-gradient(1px 1px at 74% 21%,#c8d4f0 0%,transparent 100%),
                radial-gradient(1px 1px at 88% 67%,#fff 0%,transparent 100%),
                radial-gradient(1px 1px at 93% 35%,#fff 0%,transparent 100%),
                radial-gradient(1.5px 1.5px at 27% 80%,#fff 0%,transparent 100%),
                radial-gradient(1px 1px at 55% 89%,#c8d4f0 0%,transparent 100%),
                radial-gradient(1px 1px at 82% 76%,#fff 0%,transparent 100%);
            pointer-events:none;
            z-index:0;
        }

        /* ── header ─────────────────────────────────── */
        header{
            position:relative;
            z-index:1;
            border-bottom:1px solid var(--border);
            background:linear-gradient(180deg,#0c1428 0%,var(--bg) 100%);
            padding:32px 0 28px;
            overflow:hidden;
        }

        /* moon in header corner */
        header::after{
            content:'';
            position:absolute;
            top:-100px;right:-60px;
            width:260px;height:260px;
            border-radius:50%;
            background:radial-gradient(circle at 38% 40%,
                #d0d5e0 0%,#9aa4b8 45%,#6a7488 75%,#404858 100%);
            box-shadow:0 0 40px 12px rgba(160,185,230,.1),
                       inset -18px -12px 40px rgba(0,0,0,.5);
            pointer-events:none;
            opacity:.55;
        }

        .h-inner{
            position:relative;
            z-index:1;
            max-width:1140px;
            margin:0 auto;
            padding:0 36px;
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:24px;
        }

        .h-brand{
            font-size:13px;
            font-weight:900;
            letter-spacing:-.02em;
            color:var(--muted);
            margin-bottom:16px;
        }
        .h-brand span{color:var(--accent)}

        .exc-class{
            font-size:clamp(18px,3vw,26px);
            font-weight:700;
            font-family:var(--mono);
            color:var(--bright);
            line-height:1.25;
            word-break:break-all;
            margin-bottom:8px;
        }
        .ns{color:var(--muted);font-weight:400}

        .exc-msg{
            font-size:15px;
            color:var(--text);
            margin-bottom:12px;
            max-width:700px;
            word-break:break-word;
        }

        .exc-loc{
            font-family:var(--mono);
            font-size:12px;
            color:var(--muted);
            display:inline-flex;
            align-items:center;
            gap:0;
            background:var(--surf);
            border:1px solid var(--border);
            border-radius:6px;
            padding:3px 10px;
        }
        .colon{color:var(--border);margin:0 1px}
        .lineno{color:var(--err)}

        .h-meta{
            flex-shrink:0;
            display:flex;
            flex-direction:column;
            gap:6px;
            margin-top:36px;
        }
        .meta-row{
            display:flex;
            align-items:center;
            gap:10px;
            font-family:var(--mono);
            font-size:11px;
        }
        .meta-k{
            color:var(--muted);
            letter-spacing:.08em;
            text-transform:uppercase;
            width:32px;
            text-align:right;
        }
        .meta-v{
            color:var(--accent);
            background:rgba(124,155,191,.08);
            border:1px solid var(--border);
            border-radius:4px;
            padding:1px 8px;
        }

        /* ── layout ──────────────────────────────────── */
        main{
            position:relative;
            z-index:1;
            max-width:1140px;
            margin:0 auto;
            padding:36px 36px 72px;
        }
        section{margin-bottom:44px}
        h2{
            font-size:10px;
            font-weight:700;
            letter-spacing:.14em;
            text-transform:uppercase;
            color:var(--muted);
            padding-bottom:8px;
            border-bottom:1px solid var(--border);
            margin-bottom:14px;
        }
        .muted{color:var(--muted);font-size:13px;font-style:italic}

        /* ── snippet ─────────────────────────────────── */
        .snippet{
            border:1px solid var(--border);
            border-radius:8px;
            overflow:hidden;
            font:13px/1.75 var(--mono);
            background:var(--surf);
        }
        .ln{display:flex}
        .ln:hover{background:rgba(255,255,255,.02)}
        .ln.hl{background:var(--err-dim)}
        .n{
            flex-shrink:0;
            width:52px;
            text-align:right;
            padding:0 14px 0 0;
            color:var(--muted);
            user-select:none;
            font-size:12px;
        }
        .ln.hl .n{color:var(--err-num);font-weight:700}
        .c{
            flex:1;
            padding:0 18px 0 0;
            white-space:pre;
            overflow-x:auto;
        }
        .ln.hl .c{color:var(--bright)}

        /* ── trace ───────────────────────────────────── */
        .trace{display:flex;flex-direction:column;gap:4px}

        .frame{
            border:1px solid var(--border);
            border-radius:8px;
            overflow:hidden;
            background:var(--surf);
        }
        .frame[open]>summary{border-bottom:1px solid var(--border)}

        .frame>summary{
            display:flex;
            align-items:center;
            gap:12px;
            padding:11px 16px;
            cursor:pointer;
            list-style:none;
            user-select:none;
        }
        .frame>summary:hover{background:rgba(255,255,255,.025)}
        .frame>summary::-webkit-details-marker{display:none}

        .f-n{color:var(--muted);font-size:11px;flex-shrink:0;width:22px;text-align:right}
        .f-fn{
            font-family:var(--mono);
            font-size:12.5px;
            color:var(--accent);
            flex:1;
            word-break:break-all;
        }
        .f-loc{
            font-family:var(--mono);
            font-size:11px;
            color:var(--muted);
            flex-shrink:0;
            text-align:right;
        }
        .frame-body .snippet{border:none;border-radius:0}

        /* ── request ─────────────────────────────────── */
        .req-line{
            font-family:var(--mono);
            font-size:14px;
            margin-bottom:10px;
            display:flex;
            align-items:center;
            gap:10px;
        }
        .method{
            background:var(--green);
            color:#fff;
            padding:2px 10px;
            border-radius:4px;
            font-size:11px;
            font-weight:700;
            letter-spacing:.06em;
        }
        .uri{color:var(--accent)}

        .req-group{
            border:1px solid var(--border);
            border-radius:8px;
            margin-bottom:4px;
            overflow:hidden;
            background:var(--surf);
        }
        .req-group>summary{
            padding:10px 16px;
            cursor:pointer;
            list-style:none;
            font-size:10px;
            font-weight:700;
            letter-spacing:.12em;
            text-transform:uppercase;
            color:var(--muted);
            user-select:none;
        }
        .req-group>summary:hover{background:rgba(255,255,255,.025)}
        .req-group>summary::-webkit-details-marker{display:none}
        .req-group[open]>summary{
            border-bottom:1px solid var(--border);
            color:var(--text);
        }

        .kv{width:100%;border-collapse:collapse;font-family:var(--mono);font-size:12.5px}
        .kv td{padding:7px 16px;border-bottom:1px solid var(--border);vertical-align:top}
        .kv tr:last-child td{border-bottom:none}
        .kv .k{
            color:var(--muted);
            white-space:nowrap;
            padding-right:24px;
            width:1%;
        }
        .kv .v{color:var(--text);word-break:break-all}
        CSS;
    }
}
