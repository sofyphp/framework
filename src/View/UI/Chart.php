<?php

declare(strict_types=1);

namespace Sofy\View\UI;

/**
 * SVG / CSS chart component.
 *
 * Usage:
 *   UI::chart(['Jan'=>120,'Feb'=>200,'Mar'=>150])           // bar (default)
 *   UI::chart(['Mon'=>50,'Tue'=>70,'Wed'=>60], 'line')
 *   UI::chart(['A'=>30,'B'=>50,'C'=>20], 'pie')
 *   UI::chart(['A'=>30,'B'=>50,'C'=>20], 'donut', label: 'Total')
 *
 * @param array<string,int|float> $data   label => value
 * @param string $type   bar|line|pie|donut
 * @param int    $height Container height in px (bar/line only)
 * @param string|null $label Center label text (donut only)
 * @param string[] $colors CSS color overrides; cycles through the list
 */
class Chart extends Component
{
    public function __construct(
        private readonly array   $data,
        private readonly string  $type   = 'bar',
        private readonly int     $height = 200,
        private readonly ?string $label  = null,
        private readonly array   $colors = [],
    ) {}

    private function palette(): array
    {
        return $this->colors ?: [
            'var(--accent)', 'var(--success)', 'var(--warning)',
            'var(--danger)', 'var(--info)', '#8a6bbb', '#6bbb8a',
        ];
    }

    public function render(): string
    {
        if (empty($this->data)) {
            return '<div class="sofy-chart"><div class="sofy-chart-empty">No data</div></div>';
        }

        return match ($this->type) {
            'line'  => $this->renderLine(),
            'pie'   => $this->renderPie(false),
            'donut' => $this->renderPie(true),
            default => $this->renderBar(),
        };
    }

    // ── Bar ──────────────────────────────────────────────────────────────────────

    private function renderBar(): string
    {
        $max     = max($this->data) ?: 1;
        $palette = $this->palette();
        $i       = 0;

        $cols = '';
        foreach ($this->data as $label => $value) {
            $pct   = round(($value / $max) * 100, 1);
            $color = $palette[$i % count($palette)];
            $cols .= '<div class="sofy-chart-col">'
                . '<div class="sofy-chart-bar-val">' . $this->e((string) $value) . '</div>'
                . '<div class="sofy-chart-bar-inner">'
                . '<div class="sofy-chart-bar" style="height:' . $pct . '%;background:' . $color . '" title="' . $this->e((string) $label) . ': ' . $this->e((string) $value) . '"></div>'
                . '</div>'
                . '<div class="sofy-chart-bar-lbl">' . $this->e((string) $label) . '</div>'
                . '</div>';
            $i++;
        }

        return '<div class="sofy-chart"><div class="sofy-chart-bar-wrap" style="height:' . $this->height . 'px">'
            . $cols
            . '</div></div>';
    }

    // ── Line ─────────────────────────────────────────────────────────────────────

    private function renderLine(): string
    {
        $values = array_values($this->data);
        $labels = array_keys($this->data);
        $count  = count($values);
        $max    = max($values) ?: 1;
        $min    = min($values);
        $range  = ($max - $min) ?: 1;

        $W    = 600; $H = $this->height;
        $pL   = 44; $pR = 16; $pT = 16; $pB = 34;
        $cW   = $W - $pL - $pR;
        $cH   = $H - $pT - $pB;
        $step = $count > 1 ? $cW / ($count - 1) : $cW / 2;
        $color = $this->palette()[0];

        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $x        = $pL + $i * $step;
            $y        = $pT + $cH - (($values[$i] - $min) / $range) * $cH;
            $points[] = [$x, $y];
        }

        $pStr  = implode(' ', array_map(fn($p) => round($p[0], 1) . ',' . round($p[1], 1), $points));
        $first = $points[0];
        $last  = end($points);
        $area  = $pStr . ' ' . round($last[0], 1) . ',' . ($pT + $cH) . ' ' . round($first[0], 1) . ',' . ($pT + $cH);

        $svg = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" class="sofy-chart-svg" preserveAspectRatio="xMidYMid meet">';

        // Grid
        for ($gi = 0; $gi <= 4; $gi++) {
            $gy = $pT + ($gi / 4) * $cH;
            $gv = round($max - ($gi / 4) * $range);
            $svg .= '<line x1="' . $pL . '" y1="' . round($gy, 1) . '" x2="' . ($pL + $cW) . '" y2="' . round($gy, 1) . '" class="sofy-chart-grid"/>';
            $svg .= '<text x="' . ($pL - 6) . '" y="' . round($gy + 4, 1) . '" class="sofy-chart-axis-lbl" text-anchor="end">' . $this->e((string) $gv) . '</text>';
        }

        // Area + line
        $svg .= '<polygon points="' . $this->e($area) . '" style="fill:' . $color . ';opacity:.12"/>';
        $svg .= '<polyline points="' . $this->e($pStr) . '" style="fill:none;stroke:' . $color . ';stroke-width:2;stroke-linejoin:round;stroke-linecap:round"/>';

        // Dots
        foreach ($points as $i => $p) {
            $svg .= '<circle cx="' . round($p[0], 1) . '" cy="' . round($p[1], 1) . '" r="3.5" style="fill:' . $color . '" class="sofy-chart-dot">'
                . '<title>' . $this->e((string) $labels[$i]) . ': ' . $this->e((string) $values[$i]) . '</title>'
                . '</circle>';
        }

        // X labels
        for ($i = 0; $i < $count; $i++) {
            $x = $pL + $i * $step;
            $svg .= '<text x="' . round($x, 1) . '" y="' . ($pT + $cH + 20) . '" class="sofy-chart-axis-lbl" text-anchor="middle">' . $this->e((string) $labels[$i]) . '</text>';
        }

        $svg .= '</svg>';
        return '<div class="sofy-chart">' . $svg . '</div>';
    }

    // ── Pie / Donut ───────────────────────────────────────────────────────────────

    private function renderPie(bool $donut): string
    {
        $palette = $this->palette();
        $total   = array_sum($this->data);
        if ($total <= 0) {
            return '<div class="sofy-chart"><div class="sofy-chart-empty">No data</div></div>';
        }

        $cx = 110; $cy = 110; $r = 90; $ir = $donut ? 52 : 0;
        $size = 220;

        $svg   = '<svg viewBox="0 0 ' . $size . ' ' . $size . '" class="sofy-chart-svg" preserveAspectRatio="xMidYMid meet">';
        $start = -90.0;
        $i     = 0;
        $segs  = [];

        foreach ($this->data as $lbl => $value) {
            $deg = ($value / $total) * 360;
            $end = $start + $deg;
            $la  = $deg > 180 ? 1 : 0;
            $col = $palette[$i % count($palette)];

            $x1 = $cx + $r * cos(deg2rad($start));
            $y1 = $cy + $r * sin(deg2rad($start));
            $x2 = $cx + $r * cos(deg2rad($end));
            $y2 = $cy + $r * sin(deg2rad($end));

            if ($donut) {
                $ix1 = $cx + $ir * cos(deg2rad($start));
                $iy1 = $cy + $ir * sin(deg2rad($start));
                $ix2 = $cx + $ir * cos(deg2rad($end));
                $iy2 = $cy + $ir * sin(deg2rad($end));
                $d   = "M {$x1} {$y1} A {$r} {$r} 0 {$la} 1 {$x2} {$y2} L {$ix2} {$iy2} A {$ir} {$ir} 0 {$la} 0 {$ix1} {$iy1} Z";
            } else {
                $d = "M {$cx} {$cy} L {$x1} {$y1} A {$r} {$r} 0 {$la} 1 {$x2} {$y2} Z";
            }

            $pct = round(($value / $total) * 100);
            $svg .= '<path d="' . $this->e($d) . '" style="fill:' . $col . '" class="sofy-chart-slice">'
                . '<title>' . $this->e((string) $lbl) . ': ' . $this->e((string) $value) . ' (' . $pct . '%)</title>'
                . '</path>';

            $segs[] = ['label' => $lbl, 'value' => $value, 'pct' => $pct, 'color' => $col];
            $start  = $end;
            $i++;
        }

        if ($donut) {
            $centerLabel = $this->label ?? (string) $total;
            $svg .= '<text x="' . $cx . '" y="' . ($cy - 4) . '" class="sofy-chart-center-val" text-anchor="middle">' . $this->e($centerLabel) . '</text>';
            $svg .= '<text x="' . $cx . '" y="' . ($cy + 14) . '" class="sofy-chart-center-lbl" text-anchor="middle">total</text>';
        }

        $svg .= '</svg>';

        $legend = '<div class="sofy-chart-legend">';
        foreach ($segs as $seg) {
            $legend .= '<div class="sofy-chart-legend-item">'
                . '<span class="sofy-chart-legend-dot" style="background:' . $seg['color'] . '"></span>'
                . '<span class="sofy-chart-legend-lbl">' . $this->e((string) $seg['label']) . '</span>'
                . '<span class="sofy-chart-legend-val">' . $seg['pct'] . '%</span>'
                . '</div>';
        }
        $legend .= '</div>';

        return '<div class="sofy-chart sofy-chart-pie-wrap">' . $svg . $legend . '</div>';
    }
}
