<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Sofy\View\UI\Page;

/**
 * Compile the inline design-system CSS + component JS into cached static files
 * so the browser downloads them once and reuses them across pages — instead of
 * re-embedding ~80KB inline in every single HTML response.
 *
 *   php sofy ui:build      → public/assets/sofy.<hash>.css|js + manifest
 *   php sofy ui:build --clear (or ui:clear) → remove them, back to inline
 *
 * With a manifest present, Page::render() emits <link>/<script src> to the
 * hashed files; without one it inlines (works straight from PHP, no build).
 * Re-run after upgrading the framework or changing component styles.
 */
class UiBuildCommand extends Command
{
    protected string $signature   = 'ui:build {--clear : Remove compiled assets and revert to inline}';
    protected string $description  = 'Compile UI CSS + JS into cached static assets for faster page loads';

    public function handle(): int
    {
        $base       = function_exists('base_path') ? base_path() : (string) getcwd();
        $assetsDir  = $base . '/public/assets';
        $manifest   = $base . '/bootstrap/cache/ui-manifest.php';

        if ($this->option('clear')) {
            return $this->clear($assetsDir, $manifest);
        }

        if (!is_dir($assetsDir) && !mkdir($assetsDir, 0755, true) && !is_dir($assetsDir)) {
            $this->error("Could not create $assetsDir");
            return 1;
        }
        @mkdir(dirname($manifest), 0755, true);

        $css = $this->minifyCss(Page::cssSource());
        $js  = Page::jsSource(); // left unminified — naive JS minify is unsafe

        $hash    = substr(sha1($css . '|' . $js), 0, 10);
        $cssName = "sofy.$hash.css";
        $jsName  = "sofy.$hash.js";

        // Remove older builds so the assets dir doesn't accumulate.
        foreach (glob("$assetsDir/sofy.*.css") ?: [] as $old) @unlink($old);
        foreach (glob("$assetsDir/sofy.*.js") ?: [] as $old)  @unlink($old);

        if (file_put_contents("$assetsDir/$cssName", $css) === false
            || file_put_contents("$assetsDir/$jsName", $js) === false) {
            $this->error('Could not write asset files.');
            return 1;
        }

        $payload = "<?php\n\nreturn " . var_export([
            'css'  => "/assets/$cssName",
            'js'   => "/assets/$jsName",
            'hash' => $hash,
        ], true) . ";\n";
        file_put_contents($manifest, $payload);

        $kb = static fn(string $s): string => round(strlen($s) / 1024, 1) . ' KB';
        $this->success('UI compiled.');
        $this->line("  public/assets/$cssName  ({$kb($css)})");
        $this->line("  public/assets/$jsName   ({$kb($js)})");
        $this->line('  manifest → bootstrap/cache/ui-manifest.php');
        $this->line('');
        $this->comment('Pages now load the cached CSS/JS instead of ~80KB inline per request.');
        $this->comment('Re-run after upgrading or editing component styles. `php sofy ui:clear` to revert.');
        return 0;
    }

    private function clear(string $assetsDir, string $manifest): int
    {
        $n = 0;
        foreach (array_merge(glob("$assetsDir/sofy.*.css") ?: [], glob("$assetsDir/sofy.*.js") ?: []) as $f) {
            if (@unlink($f)) $n++;
        }
        if (is_file($manifest)) {
            @unlink($manifest);
            $n++;
        }
        $this->success($n > 0 ? "Cleared compiled UI assets ($n file(s)). Pages inline CSS/JS again." : 'Nothing to clear.');
        return 0;
    }

    /** Conservative CSS minify: strip comments and collapse whitespace. */
    private function minifyCss(string $css): string
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;        // comments
        $css = preg_replace('/\s+/', ' ', $css) ?? $css;             // collapse whitespace
        $css = preg_replace('/\s*([{}:;,>])\s*/', '$1', $css) ?? $css; // around delimiters
        $css = str_replace(';}', '}', $css);
        return trim($css);
    }
}
