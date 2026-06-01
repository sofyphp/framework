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
        $loader  = Application::getInstance()->getModuleLoader();
        $modules = $loader->modules();

        if (empty($modules)) {
            $body = UI::emptyState(
                'No modules loaded',
                'Drop a folder under modules/ — Sofy auto-discovers it as long as modules/{Name}/{Name}.php is a class extending Sofy\\Core\\Module.',
                icon: '📦',
            );
        } else {
            $rows = [];
            foreach ($modules as $m) {
                $rows[] = [
                    'name'     => $m->name(),
                    'class'    => get_class($m),
                    'path'     => str_replace(Application::getInstance()->basePath() . '/', '', $m->path()),
                    'config'   => (string) count($m->config()),
                    'commands' => (string) count($m->commands()),
                ];
            }
            $body = UI::dataTable(
                ['Name', 'Class', 'Path', 'Config keys', 'Commands'],
                $rows,
                ['name', 'class', 'path', 'config', 'commands'],
                perPage: 50,
            );
        }

        return Admin::page('Modules')
            ->header('Loaded modules (' . count($modules) . ')')
            ->add(UI::card(null, $body))
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
