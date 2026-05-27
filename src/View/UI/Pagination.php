<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Pagination extends Component
{
    /**
     * @param int    $current  Current page (1-based)
     * @param int    $total    Total number of pages
     * @param string $baseUrl  URL prefix — page number is appended (e.g. '?page=')
     * @param int    $window   How many pages to show on each side of current
     */
    public function __construct(
        private readonly int    $current,
        private readonly int    $total,
        private readonly string $baseUrl = '?page=',
        private readonly int    $window  = 2,
    ) {}

    public function render(): string
    {
        if ($this->total <= 1) {
            return '';
        }

        $prev = $this->current > 1
            ? '<a href="' . $this->e($this->baseUrl . ($this->current - 1)) . '" class="sofy-page-btn">‹</a>'
            : '<span class="sofy-page-btn disabled">‹</span>';

        $next = $this->current < $this->total
            ? '<a href="' . $this->e($this->baseUrl . ($this->current + 1)) . '" class="sofy-page-btn">›</a>'
            : '<span class="sofy-page-btn disabled">›</span>';

        $pages = '';
        foreach ($this->buildPages() as $p) {
            if ($p === '…') {
                $pages .= '<span class="sofy-page-dots">…</span>';
            } elseif ($p === $this->current) {
                $pages .= '<span class="sofy-page-btn active">' . $p . '</span>';
            } else {
                $pages .= '<a href="' . $this->e($this->baseUrl . $p) . '" class="sofy-page-btn">' . $p . '</a>';
            }
        }

        return '<nav class="sofy-pages" aria-label="Pagination">'
            . $prev . $pages . $next
            . '</nav>';
    }

    /** @return array<int|string> */
    private function buildPages(): array
    {
        $start  = max(1, $this->current - $this->window);
        $end    = min($this->total, $this->current + $this->window);
        $pages  = [];

        if ($start > 1) {
            $pages[] = 1;
            if ($start > 2) {
                $pages[] = '…';
            }
        }
        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }
        if ($end < $this->total) {
            if ($end < $this->total - 1) {
                $pages[] = '…';
            }
            $pages[] = $this->total;
        }

        return $pages;
    }
}
