<?php

declare(strict_types=1);

namespace Sofy\View\UI;

use Closure;

/**
 * Client-side sortable / searchable / paginated data table.
 *
 * Usage:
 *   UI::dataTable(
 *       ['ID', 'Name', 'Email', 'Status'],
 *       $users,
 *       ['id', 'name', 'email', fn($r) => UI::badge($r['status'])],
 *       perPage: 10,
 *   )
 *
 * @param string[]                    $headers
 * @param array                       $rows
 * @param array<string|callable>|null $cols       Keys or closures; null = auto-detect
 * @param string                      $empty      Message when no rows
 * @param int                         $perPage    Rows per page (0 = show all)
 * @param bool                        $searchable Show search input
 * @param int[]                       $nosort     Column indices to disable sorting on
 */
class DataTable extends Component
{
    private static int $instances = 0;
    private readonly string $tableId;

    public function __construct(
        private readonly array   $headers,
        private readonly array   $rows,
        private readonly ?array  $cols      = null,
        private readonly string  $empty     = 'No records found.',
        private readonly int     $perPage   = 15,
        private readonly bool    $searchable = true,
        private readonly array   $nosort    = [],
    ) {
        self::$instances++;
        $this->tableId = 'sofy-dt-' . self::$instances;
    }

    public function render(): string
    {
        $id     = $this->e($this->tableId);
        $search = $this->searchable
            ? '<div class="sofy-dt-toolbar"><input class="sofy-dt-search sofy-form-ctrl" type="search" placeholder="Search…" oninput="sofyDT(\'' . $id . '\').search(this.value)" aria-label="Search table"></div>'
            : '';
        $pager  = $this->perPage > 0 ? '<div class="sofy-dt-pager" id="' . $id . '-pager"></div>' : '';
        $info   = '<div class="sofy-dt-info" id="' . $id . '-info"></div>';

        return '<div class="sofy-dt" id="' . $id . '" data-per-page="' . $this->perPage . '">'
            . $search
            . '<div class="sofy-tbl-wrap"><table class="sofy-tbl">'
            . $this->buildHead()
            . $this->buildBody()
            . '</table></div>'
            . '<div class="sofy-dt-footer">' . $info . $pager . '</div>'
            . '</div>';
    }

    private function buildHead(): string
    {
        $id   = $this->e($this->tableId);
        $html = '<thead><tr>';

        foreach ($this->headers as $i => $h) {
            $label = htmlspecialchars((string) $h, ENT_QUOTES, 'UTF-8');
            if (in_array($i, $this->nosort, true)) {
                $html .= '<th>' . $label . '</th>';
            } else {
                $html .= '<th class="sofy-dt-th" onclick="sofyDT(\'' . $id . '\').sort(' . $i . ')" data-col="' . $i . '">'
                    . $label . '<span class="sofy-dt-sort">⇅</span></th>';
            }
        }

        return $html . '</tr></thead>';
    }

    private function buildBody(): string
    {
        if (empty($this->rows)) {
            $colspan = count($this->headers);
            return '<tbody><tr><td colspan="' . $colspan . '" class="sofy-tbl-empty">'
                . htmlspecialchars($this->empty, ENT_QUOTES, 'UTF-8') . '</td></tr></tbody>';
        }

        $cols = $this->cols ?? array_keys($this->normalizeRow($this->rows[0]));
        $html = '<tbody>';

        foreach ($this->rows as $row) {
            $row   = $this->normalizeRow($row);
            $html .= '<tr class="sofy-dt-row">';

            foreach ($cols as $col) {
                if ($col instanceof Closure) {
                    $cell  = $col($row);
                    $html .= '<td>' . ($cell instanceof Component ? (string) $cell : (string) $cell) . '</td>';
                } else {
                    $val  = (string) ($row[$col] ?? '');
                    $safe = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
                    $html .= '<td data-val="' . $safe . '">' . $safe . '</td>';
                }
            }

            $html .= '</tr>';
        }

        return $html . '</tbody>';
    }

    private function normalizeRow(mixed $row): array
    {
        if (is_array($row)) {
            return $row;
        }
        if (is_object($row) && method_exists($row, 'toArray')) {
            return $row->toArray();
        }
        return (array) $row;
    }
}
