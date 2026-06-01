<?php

declare(strict_types=1);

namespace Sofy\Admin\Controllers;

use Sofy\Admin\Admin;
use Sofy\Database\Connection;
use Sofy\Database\Schema\Grammar;
use Sofy\Http\Request;
use Sofy\Http\Response;
use Sofy\View\UI;

/**
 * Live database browser + SQL console. Driver-aware via Grammar — works on
 * MySQL, PostgreSQL and SQLite without per-driver controller branches.
 *
 * Pages:
 *
 *   GET  /admin/database              list of tables (name + row count)
 *   GET  /admin/database/table/{name} columns + first 100 rows
 *   GET  /admin/database/sql          SQL console form
 *   POST /admin/database/sql          run the submitted SQL, show results
 */
class DatabaseController
{
    private const int ROW_PREVIEW_LIMIT = 100;
    private const int SQL_RESULT_LIMIT  = 500;

    public function index(): Response
    {
        $conn = $this->conn();
        if ($conn === null) {
            return $this->noConnection('Database');
        }

        $g       = Grammar::forConnection($conn);
        $tables  = array_map(static fn(array $r) => array_values($r)[0], $conn->query($g->listTablesSql()));
        sort($tables);

        $rows = [];
        foreach ($tables as $t) {
            try {
                $count = (int) (array_values($conn->query('SELECT COUNT(*) AS c FROM ' . $g->quoteId($t))[0] ?? ['c' => 0])[0]);
            } catch (\Throwable) {
                $count = 0;
            }
            $rows[] = [
                'table' => UI::raw('<a class="sofy-docs-a" href="/admin/database/table/' . rawurlencode($t) . '">' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</a>'),
                'rows'  => number_format($count),
            ];
        }

        $consoleBtn = UI::button('SQL Console', '/admin/database/sql', 'primary');

        $body = empty($rows)
            ? UI::emptyState('No tables', 'The database is reachable but has no tables — run migrations to create some.', icon: '🗄')
            : UI::dataTable(['Table', 'Rows'], $rows, ['table', 'rows'], perPage: 50);

        return Admin::page('Database')
            ->header("Tables ({$conn->getDriverName()})", $consoleBtn)
            ->add(UI::card(null, $body))
            ->response();
    }

    public function show(Request $request, string $table): Response
    {
        $conn = $this->conn();
        if ($conn === null) {
            return $this->noConnection($table);
        }

        $g = Grammar::forConnection($conn);

        try {
            $columns = $g->columnsForTable($conn, $table);
        } catch (\Throwable $e) {
            return Admin::page($table)
                ->header($table)
                ->add(UI::alert($e->getMessage(), 'danger', 'Could not read columns'))
                ->response();
        }

        // Columns table
        $colsTable = UI::table(
            ['Column', 'Type', 'Nullable', 'Default'],
            $columns,
            [
                static fn(array $c) => UI::raw('<code class="sofy-docs-code">' . htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') . '</code>'),
                'type',
                static fn(array $c) => $c['nullable'] ? UI::badge('NULL', 'info') : UI::badge('NOT NULL', 'default'),
                static fn(array $c) => $c['default'] !== null ? (string) $c['default'] : '—',
            ],
        );

        // Row preview
        try {
            $sample = $conn->query('SELECT * FROM ' . $g->quoteId($table) . ' LIMIT ' . self::ROW_PREVIEW_LIMIT);
        } catch (\Throwable $e) {
            $sample = null;
            $sampleError = $e->getMessage();
        }

        $contents = [
            UI::card('Columns (' . count($columns) . ')', $colsTable),
        ];

        if ($sample === null) {
            $contents[] = UI::card('Rows', UI::alert($sampleError ?? 'Could not read rows.', 'warning'));
        } elseif (empty($sample)) {
            $contents[] = UI::card('Rows', UI::emptyState('No rows yet', icon: '◯'));
        } else {
            $headers = array_keys($sample[0]);
            $contents[] = UI::card(
                'First ' . count($sample) . ' rows' . (count($sample) === self::ROW_PREVIEW_LIMIT ? ' (truncated)' : ''),
                UI::dataTable($headers, $sample, perPage: 25),
            );
        }

        return Admin::page($table)
            ->header($table, UI::button('← Back', '/admin/database', 'ghost'))
            ->add(...$contents)
            ->response();
    }

    public function sqlConsole(Request $request): Response
    {
        return $this->renderConsole('', null, null);
    }

    public function runSql(Request $request): Response
    {
        $sql = trim((string) $request->input('sql', ''));

        if ($sql === '') {
            return $this->renderConsole('', null, 'Enter a SQL statement first.');
        }

        $conn = $this->conn();
        if ($conn === null) {
            return $this->renderConsole($sql, null, 'No database connection.');
        }

        try {
            $first = strtolower(strtok($sql, " \t\n\r"));
            $isRead = in_array($first, ['select', 'show', 'explain', 'pragma', 'with', 'describe', 'desc'], true);

            if ($isRead) {
                $rows = $conn->query($sql);
                if (count($rows) > self::SQL_RESULT_LIMIT) {
                    $rows = array_slice($rows, 0, self::SQL_RESULT_LIMIT);
                }
                return $this->renderConsole($sql, $rows, null);
            }

            $affected = $conn->execute($sql);
            return $this->renderConsole($sql, ['__affected' => $affected], null);
        } catch (\Throwable $e) {
            return $this->renderConsole($sql, null, $e->getMessage());
        }
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private function renderConsole(string $sql, mixed $result, ?string $error): Response
    {
        $form = UI::raw(
            '<form method="POST" action="/admin/database/sql" class="sofy-sql-form">'
            . (function_exists('csrf_token') ? '<input type="hidden" name="_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">' : '')
            . '<textarea name="sql" rows="8" class="sofy-form-ctrl sofy-sql-input" placeholder="SELECT * FROM users LIMIT 10" autofocus>' . htmlspecialchars($sql, ENT_QUOTES, 'UTF-8') . '</textarea>'
            . '<div class="sofy-form-footer"><button type="submit" class="sofy-btn sofy-btn-primary">Run</button>'
            . '<span class="sofy-form-hint">Read queries (SELECT / SHOW / EXPLAIN / PRAGMA) show their result; everything else reports rows-affected.</span></div>'
            . '</form>',
        );

        $resultPane = null;
        if ($error !== null) {
            $resultPane = UI::alert($error, 'danger', 'Query failed');
        } elseif (is_array($result) && array_key_exists('__affected', $result)) {
            $resultPane = UI::alert("{$result['__affected']} row(s) affected.", 'success', 'OK');
        } elseif (is_array($result)) {
            if (empty($result)) {
                $resultPane = UI::alert('Query ran — no rows returned.', 'info', '0 rows');
            } else {
                $headers = array_keys($result[0]);
                $resultPane = UI::card(
                    count($result) . ' row(s)' . (count($result) === self::SQL_RESULT_LIMIT ? ' (truncated)' : ''),
                    UI::dataTable($headers, $result, perPage: 25),
                );
            }
        }

        $banner = UI::alert(
            'You are running raw SQL against the live application database. Treat this page like an SSH-into-prod-mysql session.',
            'warning',
            'SQL console',
        );

        $page = Admin::page('SQL Console')
            ->header('SQL Console', UI::button('← Tables', '/admin/database', 'ghost'))
            ->add(
                $banner,
                UI::card(null, $form, ''),
            );

        if ($resultPane !== null) {
            $page->add($resultPane);
        }

        // Inline a tiny CSS tweak — the textarea wants more vertical room and a monospace.
        return $page
            ->add(UI::raw(
                '<style>.sofy-sql-input{font-family:var(--mono);font-size:13px;tab-size:4;width:100%}</style>',
            ))
            ->response();
    }

    private function conn(): ?Connection
    {
        try {
            return Connection::getDefault();
        } catch (\Throwable) {
            return null;
        }
    }

    private function noConnection(string $title): Response
    {
        return Admin::page($title)
            ->header($title)
            ->add(UI::alert(
                'No database connection — check .env DB_* values, or run `php sofy migrate` to create the schema.',
                'warning',
                'Not connected',
            ))
            ->response();
    }
}
