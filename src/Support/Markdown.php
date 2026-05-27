<?php

declare(strict_types=1);

namespace Sofy\Support;

class Markdown
{
    public static function toHtml(string $md): string
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $md));
        $out   = '';
        $i     = 0;
        $n     = count($lines);

        while ($i < $n) {
            $raw = $lines[$i];

            // ── Fenced code block ─────────────────────────────────────────────
            if (preg_match('/^```(\w*)/', $raw, $m)) {
                $lang = $m[1];
                $i++;
                $code = '';
                while ($i < $n && !str_starts_with($lines[$i], '```')) {
                    $code .= $lines[$i] . "\n";
                    $i++;
                }
                $langHtml    = $lang !== ''
                    ? '<div class="sofy-code-lang">' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '</div>'
                    : '';
                $highlighted = $lang !== ''
                    ? Highlighter::highlight(rtrim($code), $lang)
                    : htmlspecialchars(rtrim($code), ENT_QUOTES, 'UTF-8');
                $out .= '<div class="sofy-code-wrap">' . $langHtml
                    . '<code class="sofy-code">' . $highlighted . '</code>'
                    . '</div>';
                $i++;
                continue;
            }

            // ── Heading ───────────────────────────────────────────────────────
            if (preg_match('/^(#{1,6})\s+(.+)$/', $raw, $m)) {
                $level = strlen($m[1]);
                $text  = self::inline($m[2]);
                $id    = self::slug(strip_tags($text));
                $out  .= "<h$level class=\"sofy-h sofy-h$level\" id=\"$id\">$text</h$level>\n";
                $i++;
                continue;
            }

            // ── Divider ───────────────────────────────────────────────────────
            if (preg_match('/^---+\s*$/', $raw)) {
                $out .= '<hr class="sofy-hr">';
                $i++;
                continue;
            }

            // ── Table ─────────────────────────────────────────────────────────
            if (str_starts_with(ltrim($raw), '|')) {
                $rows = [];
                while ($i < $n && str_starts_with(ltrim($lines[$i]), '|')) {
                    $trimmed = trim($lines[$i]);
                    if (!preg_match('/^\|[\s\-:|]+\|$/', $trimmed)) {
                        $cells  = array_map('trim', explode('|', trim($trimmed, '|')));
                        $rows[] = $cells;
                    }
                    $i++;
                }
                if (!empty($rows)) {
                    $out .= '<div class="sofy-tbl-wrap"><table class="sofy-tbl"><thead><tr>';
                    foreach (array_shift($rows) as $cell) {
                        $out .= '<th>' . self::inline($cell) . '</th>';
                    }
                    $out .= '</tr></thead><tbody>';
                    foreach ($rows as $row) {
                        $out .= '<tr>';
                        foreach ($row as $cell) {
                            $out .= '<td>' . self::inline($cell) . '</td>';
                        }
                        $out .= '</tr>';
                    }
                    $out .= '</tbody></table></div>';
                }
                continue;
            }

            // ── Unordered list ────────────────────────────────────────────────
            if (preg_match('/^[*\-]\s+(.*)$/', $raw, $m)) {
                $out .= '<ul class="sofy-ul">';
                while ($i < $n && preg_match('/^[*\-]\s+(.*)$/', $lines[$i], $m)) {
                    $out .= '<li>' . self::inline($m[1]) . '</li>';
                    $i++;
                }
                $out .= '</ul>';
                continue;
            }

            // ── Ordered list ──────────────────────────────────────────────────
            if (preg_match('/^\d+\.\s+(.*)$/', $raw, $m)) {
                $out .= '<ol class="sofy-ol">';
                while ($i < $n && preg_match('/^\d+\.\s+(.*)$/', $lines[$i], $m)) {
                    $out .= '<li>' . self::inline($m[1]) . '</li>';
                    $i++;
                }
                $out .= '</ol>';
                continue;
            }

            // ── Empty line ────────────────────────────────────────────────────
            if (trim($raw) === '') {
                $i++;
                continue;
            }

            // ── Paragraph ─────────────────────────────────────────────────────
            // Always consume the current line first so $i advances even when it
            // starts with a block-marker char (e.g. an inline `code` span or
            // "-x") that matched none of the block rules above. Without this the
            // outer while() spins forever on such a line (300s timeout).
            $para = $raw;
            $i++;
            while (
                $i < $n
                && trim($lines[$i]) !== ''
                && !preg_match('/^[#`\-*|]/', $lines[$i])
                && !preg_match('/^\d+\./', $lines[$i])
            ) {
                $para .= ' ' . $lines[$i];
                $i++;
            }
            $out .= '<p class="sofy-p">' . self::inline($para) . '</p>';
        }

        return $out;
    }

    // ── Inline markup ─────────────────────────────────────────────────────────

    private static function inline(string $text): string
    {
        $spans = [];
        $text  = preg_replace_callback('/`([^`]+)`/', function (array $m) use (&$spans): string {
            $id         = "\x00" . count($spans) . "\x00";
            $spans[$id] = '<code class="sofy-docs-code">' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</code>';
            return $id;
        }, $text);

        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/\b_(.+?)_\b/', '<em>$1</em>', $text);
        $text = preg_replace(
            '/\[([^\]]+)]\(([^)]+)\)/',
            '<a href="$2" class="sofy-docs-a">$1</a>',
            $text,
        );

        foreach ($spans as $id => $html) {
            $text = str_replace($id, $html, $text);
        }

        return $text;
    }

    private static function slug(string $text): string
    {
        $text = mb_strtolower($text);
        $text = (string) preg_replace('/[^a-z0-9\s\-]/u', '', $text);
        return (string) preg_replace('/\s+/', '-', trim($text));
    }

    /** Extract the first # heading from a markdown string. */
    public static function title(string $md): string
    {
        if (preg_match('/^#\s+(.+)/m', $md, $m)) {
            return trim($m[1]);
        }
        return '';
    }
}
