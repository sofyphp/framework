<?php

declare(strict_types=1);

namespace Sofy\Admin\Controllers;

use Sofy\Admin\Admin;
use Sofy\Core\Application;
use Sofy\Http\Response;
use Sofy\View\UI;

/**
 * Read-only system pages: framework / PHP / app info and the list of loaded
 * modules. Useful for diagnosing a production deployment without SSHing in.
 */
class SystemController
{
    public function overview(): Response
    {
        $app    = Application::getInstance();
        $driver = $this->dbDriver();

        $stats = UI::grid(4, [
            UI::stat('Sofy',     Application::version(),                description: 'framework'),
            UI::stat('PHP',      PHP_VERSION,                            description: PHP_SAPI),
            UI::stat('APP_ENV',  (string) ($app->env('APP_ENV',  '?')),  description: 'environment'),
            UI::stat('Memory',   $this->memory(),                         description: 'process peak'),
        ]);

        $appInfo = UI::card('Application', UI::kv([
            'APP_NAME'   => (string) $app->env('APP_NAME',   'Sofy'),
            'APP_URL'    => (string) $app->env('APP_URL',    '—'),
            'APP_ENV'    => (string) $app->env('APP_ENV',    '?'),
            'APP_DEBUG'  => (string) $app->env('APP_DEBUG',  'false'),
            'Base path'  => $app->basePath(),
            'Modules'    => (string) count($app->getModuleLoader()->modules()),
            'Routes'     => (string) count($app->router()->getRoutes()),
        ], layout: 'inline'));

        $phpInfo = UI::card('PHP & extensions', UI::kv([
            'Version'              => PHP_VERSION,
            'SAPI'                 => PHP_SAPI,
            'OS'                   => PHP_OS_FAMILY . ' (' . php_uname('r') . ')',
            'Server'               => (string) ($_SERVER['SERVER_SOFTWARE'] ?? '—'),
            'Memory limit'         => (string) ini_get('memory_limit'),
            'Max execution time'   => (string) ini_get('max_execution_time') . 's',
            'Opcache'              => function_exists('opcache_get_status') && @opcache_get_status(false) ? 'on' : 'off',
            'Extensions'           => (string) count(get_loaded_extensions()),
            'Database driver'      => $driver,
        ], layout: 'inline'));

        $extensions = $this->extensionsCard();

        return Admin::page('System')
            ->header('System')
            ->add(
                $stats,
                UI::grid(2, [$appInfo, $phpInfo]),
                $extensions,
            )
            ->response();
    }

    public function modules(): Response
    {
        $loader   = Application::getInstance()->getModuleLoader();
        $base     = Application::getInstance()->basePath();
        $modules  = $loader->modules();
        $disabled = $loader->disabled();   // folders present but NOT in bootstrap/modules.php
        $failed   = $loader->failed();     // tried to load/register but threw

        // ── Enabled & loaded ────────────────────────────────────────────────
        if (empty($modules)) {
            $loadedCard = UI::card(
                'Loaded (0)',
                UI::emptyState(
                    'No modules loaded',
                    'Drop a folder under modules/ and run `php sofy module:install {Name}` to enable it.',
                    icon: '📦',
                ),
            );
        } else {
            $rows = [];
            foreach ($modules as $m) {
                $rows[] = [
                    'name'     => $m->name(),
                    'class'    => get_class($m),
                    'path'     => str_replace($base . '/', '', $m->path()),
                    'config'   => (string) count($m->config()),
                    'commands' => (string) count($m->commands()),
                ];
            }
            $loadedCard = UI::card(
                'Loaded (' . count($modules) . ')',
                UI::dataTable(
                    ['Name', 'Class', 'Path', 'Config keys', 'Commands'],
                    $rows,
                    ['name', 'class', 'path', 'config', 'commands'],
                    perPage: 50,
                ),
            );
        }

        // ── Discoverable but not enabled ────────────────────────────────────
        $disabledCard = null;
        if (!empty($disabled)) {
            $disabledRows = array_map(static fn(string $n): array => [
                'name' => $n,
                'path' => "modules/{$n}/",
                'hint' => UI::raw('<code class="sofy-docs-code">php sofy module:install ' . htmlspecialchars($n, ENT_QUOTES, 'UTF-8') . '</code>'),
            ], $disabled);
            $disabledCard = UI::card(
                'Discovered but not enabled (' . count($disabled) . ')',
                UI::table(
                    ['Folder', 'Path', 'Enable command'],
                    $disabledRows,
                    ['name', 'path', 'hint'],
                ),
            );
        }

        // ── Failed during load / register / routes / boot ───────────────────
        $failedCard = null;
        if (!empty($failed)) {
            $failedRows = [];
            foreach ($failed as $name => $err) {
                $failedRows[] = [
                    'name'    => $name,
                    'message' => (string) $err->getMessage(),
                    'where'   => str_replace($base . '/', '', $err->getFile()) . ':' . $err->getLine(),
                ];
            }
            $failedCard = UI::card(
                'Failed (' . count($failed) . ')',
                UI::table(
                    ['Module', 'Error', 'Location'],
                    $failedRows,
                    [
                        fn(array $r) => UI::raw('<strong>' . htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') . '</strong>'),
                        fn(array $r) => UI::raw('<code class="sofy-docs-code">' . htmlspecialchars($r['message'], ENT_QUOTES, 'UTF-8') . '</code>'),
                        fn(array $r) => UI::raw('<code class="sofy-docs-code">' . htmlspecialchars($r['where'], ENT_QUOTES, 'UTF-8') . '</code>'),
                    ],
                ),
            );
        }

        $components = array_filter([
            $failedCard !== null ? UI::alert(
                UI::raw('<strong>' . count($failed) . '</strong> module(s) failed during boot. Framework continues with the rest.'),
                'danger',
                'Some modules failed to load',
            ) : null,
            $loadedCard,
            $disabledCard,
            $failedCard,
        ]);

        return Admin::page('Modules')
            ->header('Modules (' . count($modules) . ' loaded · ' . count($disabled) . ' disabled · ' . count($failed) . ' failed)')
            ->add(...$components)
            ->response();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function memory(): string
    {
        $mb = memory_get_peak_usage() / 1024 / 1024;
        return number_format($mb, 1) . ' MB';
    }

    private function dbDriver(): string
    {
        try {
            return \Sofy\Database\Connection::getDefault()->getDriverName();
        } catch (\Throwable) {
            return '— (not connected)';
        }
    }

    private function extensionsCard(): \Sofy\View\UI\Card
    {
        $exts = get_loaded_extensions();
        sort($exts);
        $tags = array_map(static fn(string $e) => UI::tag($e), $exts);
        return UI::card('Loaded extensions (' . count($exts) . ')', UI::tags($tags));
    }
}
