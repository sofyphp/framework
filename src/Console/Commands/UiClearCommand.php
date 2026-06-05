<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

/**
 * Remove compiled UI assets and revert to inline rendering — the counterpart
 * to `php sofy ui:build`. (Same as `ui:build --clear`.)
 */
class UiClearCommand extends Command
{
    protected string $signature   = 'ui:clear';
    protected string $description  = 'Remove compiled UI assets (revert to inline CSS/JS)';

    public function handle(): int
    {
        $base      = function_exists('base_path') ? base_path() : (string) getcwd();
        $assetsDir = $base . '/public/assets';
        $manifest  = $base . '/bootstrap/cache/ui-manifest.php';

        $n = 0;
        foreach (array_merge(glob("$assetsDir/sofy.*.css") ?: [], glob("$assetsDir/sofy.*.js") ?: []) as $f) {
            if (@unlink($f)) $n++;
        }
        if (is_file($manifest) && @unlink($manifest)) {
            $n++;
        }

        $this->success($n > 0
            ? "Cleared compiled UI assets ($n file(s)). Pages inline CSS/JS again."
            : 'Nothing to clear — no compiled assets.');
        return 0;
    }
}
