<?php

declare(strict_types=1);

namespace Sofy\View\UI;

use Closure;

class Table extends Component
{
    /**
     * @param string[]              $headers  Column labels
     * @param array                 $rows     Array of arrays/objects
     * @param array<string|callable>|null $cols  Keys or closures per column
     * @param string                $empty    Text when rows is empty
     */
    public function __construct(
        private readonly array   $headers,
        private readonly array   $rows,
        private readonly ?array  $cols  = null,
        private readonly string  $empty = 'No records found.',
    ) {}

    public function render(): string
    {
        $head = '<thead><tr>'
            . implode('', array_map(fn($h) => '<th>' . $this->e($h) . '</th>', $this->headers))
            . '</tr></thead>';

        if (empty($this->rows)) {
            $colspan = count($this->headers);
            $body    = '<tbody><tr><td colspan="' . $colspan . '" class="sofy-tbl-empty">'
                . $this->e($this->empty) . '</td></tr></tbody>';
            return '<div class="sofy-tbl-wrap"><table class="sofy-tbl">' . $head . $body . '</table></div>';
        }

        $cols = $this->cols ?? array_keys($this->normalizeRow($this->rows[0]));
        $body = '<tbody>';

        foreach ($this->rows as $row) {
            $row  = $this->normalizeRow($row);
            $body .= '<tr>';
            foreach ($cols as $col) {
                if ($col instanceof Closure) {
                    // Closure return values are trusted — Component or raw HTML string
                    $cell  = $col($row);
                    $body .= '<td>' . $cell . '</td>';
                } else {
                    // Plain key — render Component as HTML, escape scalars
                    $value = $row[$col] ?? '';
                    $body .= $value instanceof Component
                        ? '<td>' . $value . '</td>'
                        : '<td>' . $this->e((string) $value) . '</td>';
                }
            }
            $body .= '</tr>';
        }

        $body .= '</tbody>';

        return '<div class="sofy-tbl-wrap"><table class="sofy-tbl">' . $head . $body . '</table></div>';
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
