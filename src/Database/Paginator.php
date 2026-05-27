<?php

declare(strict_types=1);

namespace Sofy\Database;

class Paginator
{
    private int $lastPage;

    public function __construct(
        private readonly array $items,
        private readonly int   $total,
        private readonly int   $perPage,
        private readonly int   $currentPage,
    ) {
        $this->lastPage = max(1, (int) ceil($total / $perPage));
    }

    public function items(): array       { return $this->items; }
    public function total(): int         { return $this->total; }
    public function perPage(): int       { return $this->perPage; }
    public function currentPage(): int   { return $this->currentPage; }
    public function lastPage(): int      { return $this->lastPage; }
    public function hasMorePages(): bool { return $this->currentPage < $this->lastPage; }
    public function hasPrevPage(): bool  { return $this->currentPage > 1; }
    public function nextPage(): ?int     { return $this->hasMorePages() ? $this->currentPage + 1 : null; }
    public function prevPage(): ?int     { return $this->hasPrevPage() ? $this->currentPage - 1 : null; }
    public function isEmpty(): bool      { return empty($this->items); }
    public function isNotEmpty(): bool   { return !empty($this->items); }

    public function links(string $pageParam = 'page'): string
    {
        if ($this->lastPage <= 1) return '';

        $base   = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        $params = $_GET;
        $html   = '<nav class="pagination"><ul>';

        if ($this->hasPrevPage()) {
            $params[$pageParam] = $this->currentPage - 1;
            $html .= '<li><a href="' . $base . '?' . http_build_query($params) . '">&laquo;</a></li>';
        }

        foreach ($this->range() as $p) {
            if ((string) $p === '…') {
                $html .= '<li><span>&hellip;</span></li>';
                continue;
            }
            $params[$pageParam] = $p;
            $active = $p === $this->currentPage ? ' class="active"' : '';
            $html  .= "<li$active><a href=\"$base?" . http_build_query($params) . "\">$p</a></li>";
        }

        if ($this->hasMorePages()) {
            $params[$pageParam] = $this->currentPage + 1;
            $html .= '<li><a href="' . $base . '?' . http_build_query($params) . '">&raquo;</a></li>';
        }

        return $html . '</ul></nav>';
    }

    public function toArray(): array
    {
        return [
            'data'         => $this->items,
            'total'        => $this->total,
            'per_page'     => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page'    => $this->lastPage,
            'has_more'     => $this->hasMorePages(),
        ];
    }

    /** Строит компактный диапазон страниц: 1 … 4 5 6 … 20 */
    private function range(): array
    {
        $last = $this->lastPage;
        $cur  = $this->currentPage;

        if ($last <= 7) return range(1, $last);

        $pages = [1];
        if ($cur > 4) $pages[] = '…';

        for ($i = max(2, $cur - 2); $i <= min($last - 1, $cur + 2); $i++) {
            $pages[] = $i;
        }

        if ($cur < $last - 3) $pages[] = '…';
        $pages[] = $last;

        return $pages;
    }
}
