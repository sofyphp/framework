<?php

declare(strict_types=1);

namespace Sofy\Support;

class Str
{
    // ── Case ─────────────────────────────────────────────────────────────────

    public static function camel(string $str): string
    {
        return lcfirst(static::studly($str));
    }

    public static function studly(string $str): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $str)));
    }

    public static function snake(string $str, string $delimiter = '_'): string
    {
        $str = preg_replace('/\s+/u', '', ucwords($str)) ?? $str;
        $str = preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $str) ?? $str;
        return mb_strtolower($str);
    }

    public static function kebab(string $str): string
    {
        return static::snake($str, '-');
    }

    public static function upper(string $str): string
    {
        return mb_strtoupper($str, 'UTF-8');
    }

    public static function lower(string $str): string
    {
        return mb_strtolower($str, 'UTF-8');
    }

    public static function title(string $str): string
    {
        return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
    }

    // ── Slug ─────────────────────────────────────────────────────────────────

    public static function slug(string $str, string $separator = '-'): string
    {
        $str = mb_strtolower($str, 'UTF-8');
        $str = preg_replace('/[^\pL\pN\s-]/u', '', $str) ?? $str;
        $str = preg_replace('/[\s-]+/', $separator, trim($str)) ?? $str;
        return trim($str, $separator);
    }

    // ── Search / test ─────────────────────────────────────────────────────────

    public static function contains(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }

    public static function containsAll(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (!str_contains($haystack, $needle)) {
                return false;
            }
        }
        return true;
    }

    public static function startsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if (str_starts_with($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }

    public static function endsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if (str_ends_with($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }

    public static function isJson(string $str): bool
    {
        if ($str === '') {
            return false;
        }
        json_decode($str);
        return json_last_error() === JSON_ERROR_NONE;
    }

    public static function isEmail(string $str): bool
    {
        return filter_var($str, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function isUrl(string $str): bool
    {
        return filter_var($str, FILTER_VALIDATE_URL) !== false;
    }

    public static function isUuid(string $str): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $str
        );
    }

    // ── Extract ───────────────────────────────────────────────────────────────

    public static function before(string $str, string $needle): string
    {
        if ($needle === '') {
            return $str;
        }
        $pos = strpos($str, $needle);
        return $pos === false ? $str : substr($str, 0, $pos);
    }

    public static function beforeLast(string $str, string $needle): string
    {
        if ($needle === '') {
            return $str;
        }
        $pos = strrpos($str, $needle);
        return $pos === false ? $str : substr($str, 0, $pos);
    }

    public static function after(string $str, string $needle): string
    {
        if ($needle === '' || !str_contains($str, $needle)) {
            return $str;
        }
        return substr($str, strpos($str, $needle) + strlen($needle));
    }

    public static function afterLast(string $str, string $needle): string
    {
        if ($needle === '' || !str_contains($str, $needle)) {
            return $str;
        }
        return substr($str, strrpos($str, $needle) + strlen($needle));
    }

    public static function between(string $str, string $from, string $to): string
    {
        return static::before(static::after($str, $from), $to);
    }

    public static function betweenFirst(string $str, string $from, string $to): string
    {
        return static::before(static::after($str, $from), $to);
    }

    // ── Limit ─────────────────────────────────────────────────────────────────

    public static function limit(string $str, int $limit = 100, string $end = '...'): string
    {
        if (mb_strwidth($str, 'UTF-8') <= $limit) {
            return $str;
        }
        return mb_strimwidth($str, 0, $limit, $end, 'UTF-8');
    }

    public static function words(string $str, int $words = 100, string $end = '...'): string
    {
        preg_match('/^\s*+(?:\S+\s*+){1,' . $words . '}/u', $str, $matches);
        if (!isset($matches[0]) || mb_strlen($str) === mb_strlen($matches[0])) {
            return $str;
        }
        return rtrim($matches[0]) . $end;
    }

    // ── Mask / pad ────────────────────────────────────────────────────────────

    public static function mask(string $str, string $character = '*', int $index = 0, ?int $length = null): string
    {
        $segment = mb_substr($str, $index, $length, 'UTF-8');
        if ($segment === '') {
            return $str;
        }
        $masked = str_repeat($character[0], mb_strlen($segment, 'UTF-8'));
        return mb_substr($str, 0, $index, 'UTF-8') . $masked
             . mb_substr($str, $index + mb_strlen($segment, 'UTF-8'), null, 'UTF-8');
    }

    public static function padLeft(string $str, int $length, string $pad = ' '): string
    {
        return str_pad($str, $length, $pad, STR_PAD_LEFT);
    }

    public static function padRight(string $str, int $length, string $pad = ' '): string
    {
        return str_pad($str, $length, $pad, STR_PAD_RIGHT);
    }

    public static function padBoth(string $str, int $length, string $pad = ' '): string
    {
        return str_pad($str, $length, $pad, STR_PAD_BOTH);
    }

    // ── Mutate ────────────────────────────────────────────────────────────────

    public static function remove(string $str, string|array $search, bool $caseSensitive = true): string
    {
        return $caseSensitive
            ? str_replace($search, '', $str)
            : str_ireplace($search, '', $str);
    }

    public static function replace(string $str, string|array $search, string|array $replace, bool $caseSensitive = true): string
    {
        return $caseSensitive
            ? str_replace($search, $replace, $str)
            : str_ireplace($search, $replace, $str);
    }

    public static function replaceFirst(string $str, string $search, string $replace): string
    {
        if ($search === '') {
            return $str;
        }
        $pos = strpos($str, $search);
        return $pos === false ? $str : substr_replace($str, $replace, $pos, strlen($search));
    }

    public static function replaceLast(string $str, string $search, string $replace): string
    {
        if ($search === '') {
            return $str;
        }
        $pos = strrpos($str, $search);
        return $pos === false ? $str : substr_replace($str, $replace, $pos, strlen($search));
    }

    public static function reverse(string $str): string
    {
        return implode('', array_reverse(mb_str_split($str, 1, 'UTF-8')));
    }

    public static function repeat(string $str, int $times): string
    {
        return str_repeat($str, $times);
    }

    public static function squish(string $str): string
    {
        return preg_replace('/\s+/', ' ', trim($str)) ?? $str;
    }

    public static function wrap(string $str, string $before, string $after = ''): string
    {
        return $before . $str . ($after ?: $before);
    }

    public static function unwrap(string $str, string $before, string $after = ''): string
    {
        $after = $after ?: $before;
        if (str_starts_with($str, $before) && str_ends_with($str, $after)) {
            return substr($str, strlen($before), -strlen($after));
        }
        return $str;
    }

    // ── Plural / singular ─────────────────────────────────────────────────────

    public static function plural(string $word, int $count = 2): string
    {
        if ($count === 1) {
            return $word;
        }
        foreach (self::pluralIrregulars() as $singular => $plural) {
            if (strcasecmp($word, $singular) === 0) {
                return $plural;
            }
        }
        foreach (self::pluralRules() as $pattern => $replacement) {
            if (preg_match($pattern, $word)) {
                return preg_replace($pattern, $replacement, $word) ?? $word;
            }
        }
        return $word . 's';
    }

    public static function singular(string $word): string
    {
        foreach (self::pluralIrregulars() as $singular => $plural) {
            if (strcasecmp($word, $plural) === 0) {
                return $singular;
            }
        }
        foreach (self::singularRules() as $pattern => $replacement) {
            if (preg_match($pattern, $word)) {
                return preg_replace($pattern, $replacement, $word) ?? $word;
            }
        }
        return $word;
    }

    private static function pluralRules(): array
    {
        return [
            '/(quiz)$/i'               => '$1zes',
            '/^(oxen)$/i'              => '$1',
            '/^(ox)$/i'                => '$1en',
            '/([m|l])ice$/i'           => '$1ice',
            '/([m|l])ouse$/i'          => '$1ice',
            '/(pea)ple$/i'             => '$1ple',
            '/(pe)rson$/i'             => '$1ople',
            '/(child)ren$/i'           => '$1ren',
            '/(child)$/i'              => '$1ren',
            '/(x|ch|ss|sh)$/i'         => '$1es',
            '/([^aeiouy]|qu)ies$/i'    => '$1ies',
            '/([^aeiouy]|qu)y$/i'      => '$1ies',
            '/(hive)s$/i'              => '$1s',
            '/(hive)$/i'               => '$1s',
            '/([lr])ves$/i'            => '$1ves',
            '/([ti])a$/i'              => '$1a',
            '/([ti])um$/i'             => '$1a',
            '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '$1ses',
            '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)sis$/i' => '$1ses',
            '/([^f])ves$/i'            => '$1ves',
            '/([^f])fe$/i'             => '$1ves',
            '/([^f])f$/i'              => '$1ves',
            '/(shea|lea|loa|thie)ves$/i' => '$1ves',
            '/(shea|lea|loa|thie)f$/i'   => '$1ves',
            '/sis$/i'                  => 'ses',
            '/([aeiou]o)es$/i'         => '$1es',
            '/([aeiou]o)$/i'           => '$1es',
            '/(bu)ses$/i'              => '$1ses',
            '/(bu)s$/i'                => '$1ses',
            '/(alias)es$/i'            => '$1es',
            '/(alias)$/i'              => '$1es',
            '/(octop|vir)i$/i'         => '$1i',
            '/(octop|vir)us$/i'        => '$1i',
            '/(ax|test)es$/i'          => '$1es',
            '/(ax|test)is$/i'          => '$1es',
            '/s$/i'                    => 's',
        ];
    }

    private static function singularRules(): array
    {
        return [
            '/(quiz)zes$/i'              => '$1',
            '/(matr)ices$/i'             => '$1ix',
            '/(vert|ind)ices$/i'         => '$1ex',
            '/^(ox)en/i'                 => '$1',
            '/(alias)es$/i'              => '$1',
            '/(octop|vir)i$/i'           => '$1us',
            '/(cris|ax|test)es$/i'       => '$1is',
            '/(shoe)s$/i'                => '$1',
            '/(o)es$/i'                  => '$1',
            '/(bus)es$/i'                => '$1',
            '/([m|l])ice$/i'             => '$1ouse',
            '/(x|ch|ss|sh)es$/i'         => '$1',
            '/(m)ovies$/i'               => '$1ovie',
            '/(s)eries$/i'               => '$1eries',
            '/([^aeiouy]|qu)ies$/i'      => '$1y',
            '/([lr])ves$/i'              => '$1f',
            '/(thi|shea|lea|loa)ves$/i'  => '$1f',
            '/(^analy)ses$/i'            => '$1sis',
            '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '$1sis',
            '/([ti])a$/i'                => '$1um',
            '/(p)eople$/i'               => '$1erson',
            '/(m)en$/i'                  => '$1an',
            '/(c)hildren$/i'             => '$1hild',
            '/(n)ews$/i'                 => '$1ews',
            '/s$/i'                      => '',
        ];
    }

    private static function pluralIrregulars(): array
    {
        return [
            'child'  => 'children',
            'goose'  => 'geese',
            'man'    => 'men',
            'woman'  => 'women',
            'tooth'  => 'teeth',
            'foot'   => 'feet',
            'mouse'  => 'mice',
            'person' => 'people',
        ];
    }

    // ── Random / UUID ──────────────────────────────────────────────────────────

    public static function random(int $length = 16): string
    {
        $bytes = random_bytes((int) ceil($length * 3 / 4));
        return substr(str_replace(['+', '/', '='], ['', '', ''], base64_encode($bytes)), 0, $length);
    }

    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    public static function orderedUuid(): string
    {
        $time  = dechex((int) (microtime(true) * 1000));
        $time  = str_pad($time, 12, '0', STR_PAD_LEFT);
        $bytes = random_bytes(10);
        $hex   = $time . bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            '7' . substr($hex, 13, 3),
            dechex(hexdec(substr($hex, 16, 2)) | 0x80) . substr($hex, 18, 2),
            substr($hex, 20, 12)
        );
    }

    // ── Word / char count ─────────────────────────────────────────────────────

    public static function wordCount(string $str): int
    {
        return str_word_count(strip_tags($str));
    }

    public static function length(string $str): int
    {
        return mb_strlen($str, 'UTF-8');
    }

    public static function charAt(string $str, int $index): string
    {
        return mb_substr($str, $index, 1, 'UTF-8');
    }

    public static function substrCount(string $str, string $needle): int
    {
        return substr_count($str, $needle);
    }

    // ── Fluent builder ────────────────────────────────────────────────────────

    public static function of(string $str): Stringable
    {
        return new Stringable($str);
    }
}
