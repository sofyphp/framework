<?php

declare(strict_types=1);

namespace Sofy\Support;

class Highlighter
{
    public static function highlight(string $code, string $lang): string
    {
        return match ($lang) {
            'php'        => self::php($code),
            'sql'        => self::tokenize($code, self::sqlPatterns()),
            'bash', 'sh' => self::tokenize($code, self::bashPatterns()),
            'json'       => self::tokenize($code, self::jsonPatterns()),
            default      => htmlspecialchars($code, ENT_QUOTES, 'UTF-8'),
        };
    }

    // ── PHP — uses built-in tokenizer ─────────────────────────────────────────

    private static function php(string $code): string
    {
        $hasPHP = (bool) preg_match('/^\s*<\?/', $code);
        $src    = $hasPHP ? $code : "<?php\n" . $code;

        try {
            $tokens = @token_get_all($src);
        } catch (\Throwable) {
            return htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        }

        static $kwTokens = null;
        if ($kwTokens === null) {
            $kwTokens = array_filter([
                T_CLASS, T_FUNCTION, T_RETURN, T_IF, T_ELSE, T_ELSEIF,
                T_FOREACH, T_FOR, T_WHILE, T_DO, T_SWITCH, T_CASE, T_DEFAULT,
                T_BREAK, T_CONTINUE, T_NEW, T_USE, T_NAMESPACE, T_PUBLIC,
                T_PRIVATE, T_PROTECTED, T_STATIC, T_ABSTRACT, T_FINAL,
                T_INTERFACE, T_EXTENDS, T_IMPLEMENTS, T_ECHO, T_PRINT,
                T_TRY, T_CATCH, T_FINALLY, T_THROW, T_DECLARE, T_LIST,
                T_YIELD, T_INCLUDE, T_REQUIRE, T_INCLUDE_ONCE, T_REQUIRE_ONCE,
                T_TRAIT, T_ARRAY, T_CALLABLE,
                defined('T_FN')       ? T_FN       : 0,
                defined('T_MATCH')    ? T_MATCH    : 0,
                defined('T_ENUM')     ? T_ENUM     : 0,
                defined('T_READONLY') ? T_READONLY : 0,
            ]);
        }

        $out     = '';
        $skipPHP = !$hasPHP;

        foreach ($tokens as $token) {
            if (is_string($token)) {
                $out .= htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
                continue;
            }

            [$type, $text] = $token;

            // Skip the <?php we prepended
            if ($skipPHP && $type === T_OPEN_TAG) {
                $skipPHP = false;
                continue;
            }

            $esc = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

            $out .= match (true) {
                $type === T_COMMENT || $type === T_DOC_COMMENT
                    => '<span class="hl-cmt">' . $esc . '</span>',

                $type === T_CONSTANT_ENCAPSED_STRING || $type === T_ENCAPSED_AND_WHITESPACE
                    => '<span class="hl-str">' . $esc . '</span>',

                $type === T_START_HEREDOC || $type === T_END_HEREDOC
                    => '<span class="hl-str">' . $esc . '</span>',

                $type === T_VARIABLE
                    => '<span class="hl-var">' . $esc . '</span>',

                $type === T_LNUMBER || $type === T_DNUMBER
                    => '<span class="hl-num">' . $esc . '</span>',

                in_array($type, $kwTokens, true)
                    => '<span class="hl-kw">' . $esc . '</span>',

                $type === T_STRING && in_array(strtolower($text), ['null', 'true', 'false'], true)
                    => '<span class="hl-lit">' . $esc . '</span>',

                $type === T_OPEN_TAG || $type === T_OPEN_TAG_WITH_ECHO || $type === T_CLOSE_TAG
                    => '<span class="hl-tag">' . $esc . '</span>',

                $type === T_ATTRIBUTE
                    => '<span class="hl-attr">' . $esc . '</span>',

                default => $esc,
            };
        }

        return $out;
    }

    // ── Generic regex tokenizer ───────────────────────────────────────────────

    /**
     * Walk through $code left-to-right, matching $patterns in priority order.
     * $patterns: [ regex_fragment => css_class, ... ]
     */
    private static function tokenize(string $code, array $patterns): string
    {
        // Build one alternation with named capture groups
        $groupClass = [];
        $alts       = [];
        $gIdx       = 0;

        foreach ($patterns as $pattern => $class) {
            $name             = 'g' . $gIdx++;
            $alts[]           = '(?P<' . $name . '>' . $pattern . ')';
            $groupClass[$name] = $class;
        }

        $regex = '/' . implode('|', $alts) . '/is';
        $out   = '';
        $pos   = 0;
        $len   = strlen($code);

        while ($pos < $len) {
            if (!preg_match($regex, $code, $m, PREG_OFFSET_CAPTURE, $pos)) {
                break;
            }

            $matchStart = (int) $m[0][1];
            $matchText  = (string) $m[0][0];

            // Unmatched text before this match
            if ($matchStart > $pos) {
                $out .= htmlspecialchars(substr($code, $pos, $matchStart - $pos), ENT_QUOTES, 'UTF-8');
            }

            // Identify which named group matched
            $class = '';
            foreach ($groupClass as $name => $cls) {
                if (isset($m[$name]) && $m[$name][1] !== -1 && $m[$name][0] !== '') {
                    $class = $cls;
                    break;
                }
            }

            $esc  = htmlspecialchars($matchText, ENT_QUOTES, 'UTF-8');
            $out .= $class !== '' ? '<span class="' . $class . '">' . $esc . '</span>' : $esc;

            $advance = strlen($matchText);
            $pos     = $matchStart + ($advance > 0 ? $advance : 1);
        }

        // Remaining unmatched text
        if ($pos < $len) {
            $out .= htmlspecialchars(substr($code, $pos), ENT_QUOTES, 'UTF-8');
        }

        return $out;
    }

    // ── Language pattern sets ─────────────────────────────────────────────────

    private static function sqlPatterns(): array
    {
        $kw = 'SELECT|DISTINCT|FROM|WHERE|JOIN|LEFT|RIGHT|INNER|OUTER|FULL|CROSS|ON|'
            . 'GROUP\s+BY|ORDER\s+BY|HAVING|LIMIT|OFFSET|AS|AND|OR|NOT|IN|EXISTS|LIKE|'
            . 'BETWEEN|IS|NULL|INSERT|INTO|VALUES|UPDATE|SET|DELETE|TRUNCATE|'
            . 'CREATE|TABLE|INDEX|VIEW|DROP|ALTER|ADD|COLUMN|MODIFY|'
            . 'PRIMARY\s+KEY|FOREIGN\s+KEY|REFERENCES|UNIQUE|DEFAULT|CONSTRAINT|'
            . 'COUNT|MAX|MIN|AVG|SUM|COALESCE|IFNULL|CAST|CONVERT|'
            . 'BEGIN|COMMIT|ROLLBACK|TRANSACTION|AUTO_INCREMENT|IF\s+NOT\s+EXISTS';

        return [
            '--[^\n]*|\/\*[\s\S]*?\*\/'   => 'hl-cmt',
            "'(?:[^'\\\\]|\\\\.)*'"        => 'hl-str',
            '"(?:[^"\\\\]|\\\\.)*"'        => 'hl-str',
            '\b(?:' . $kw . ')\b'          => 'hl-kw',
            '\b\d+(?:\.\d+)?\b'            => 'hl-num',
            '\b(?:TRUE|FALSE|NULL)\b'      => 'hl-lit',
        ];
    }

    private static function bashPatterns(): array
    {
        return [
            '#[^\n]*'                       => 'hl-cmt',
            "'[^']*'"                       => 'hl-str',
            '"(?:[^"\\\\]|\\\\.)*"'         => 'hl-str',
            '\$\{[^}]*\}|\$\w+'             => 'hl-var',
            '(?<!\w)--?[\w-]+'              => 'hl-flag',
            '\b\d+\b'                       => 'hl-num',
            '\b(php|composer|git|npm|curl|wget|echo|cd|ls|mkdir|rm|cp|mv)\b' => 'hl-kw',
        ];
    }

    private static function jsonPatterns(): array
    {
        return [
            '"(?:[^"\\\\]|\\\\.)*"\s*:'     => 'hl-prop',   // key
            '"(?:[^"\\\\]|\\\\.)*"'         => 'hl-str',    // string value
            '\b\d+(?:\.\d+)?\b'             => 'hl-num',
            '\b(?:true|false|null)\b'        => 'hl-lit',
        ];
    }
}
