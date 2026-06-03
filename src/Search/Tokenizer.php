<?php

declare(strict_types=1);

namespace Sofy\Search;

/**
 * Turns free text into normalized search terms. Configured from
 * config('search.tokenizer'). Zero-dependency — no ext-intl required; accent
 * folding is a hand-rolled transliteration table covering Latin-1 + common
 * Cyrillic so it works on a bare PHP install.
 */
final class Tokenizer
{
    private bool $lowercase;
    private bool $unaccent;
    private int  $minLength;
    /** @var array<string,true> */
    private array $stopwords;

    /** @param array<string,mixed> $config */
    public function __construct(array $config = [])
    {
        $this->lowercase = (bool) ($config['lowercase'] ?? true);
        $this->unaccent  = (bool) ($config['unaccent'] ?? true);
        $this->minLength = (int)  ($config['min_length'] ?? 2);
        $this->stopwords = $this->resolveStopwords($config['stopwords'] ?? 'en');
    }

    /**
     * Tokenize text into a de-duplicated list of normalized terms.
     *
     * @return list<string>
     */
    public function tokenize(string $text): array
    {
        $text = $this->normalize($text);
        // Split on anything that isn't a letter or digit (Unicode-aware).
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $out = [];
        foreach ($parts as $tok) {
            if (mb_strlen($tok) < $this->minLength) {
                continue;
            }
            if (isset($this->stopwords[$tok])) {
                continue;
            }
            $out[$tok] = true; // de-dupe
        }
        return array_keys($out);
    }

    /**
     * Term frequencies for a piece of text: term => count. Used to weight a
     * field at index time (a term appearing 3× scores higher than once).
     *
     * @return array<string,int>
     */
    public function frequencies(string $text): array
    {
        $text  = $this->normalize($text);
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $freq = [];
        foreach ($parts as $tok) {
            if (mb_strlen($tok) < $this->minLength || isset($this->stopwords[$tok])) {
                continue;
            }
            $freq[$tok] = ($freq[$tok] ?? 0) + 1;
        }
        return $freq;
    }

    /** Normalize a single string without splitting — lowercase + unaccent. */
    public function normalize(string $text): string
    {
        if ($this->lowercase) {
            $text = mb_strtolower($text, 'UTF-8');
        }
        if ($this->unaccent) {
            $text = strtr($text, self::TRANSLIT);
        }
        return $text;
    }

    /** @param string|list<string> $spec */
    private function resolveStopwords(string|array $spec): array
    {
        if (is_array($spec)) {
            return $this->index(array_map(fn($w) => $this->normalize((string) $w), $spec));
        }
        $list = match ($spec) {
            'en'    => self::STOP_EN,
            'ru'    => self::STOP_RU,
            default => [],
        };
        return $this->index($list);
    }

    /** @param list<string> $words @return array<string,true> */
    private function index(array $words): array
    {
        $out = [];
        foreach ($words as $w) {
            if ($w !== '') $out[$w] = true;
        }
        return $out;
    }

    /** Minimal accent/transliteration fold — Latin-1 + common Cyrillic. */
    private const array TRANSLIT = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae',
        'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss',
        'ё' => 'е', // Russian yo → ye, the most common normalization
    ];

    private const array STOP_EN = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'has', 'he',
        'in', 'is', 'it', 'its', 'of', 'on', 'or', 'that', 'the', 'to', 'was', 'were',
        'will', 'with',
    ];

    private const array STOP_RU = [
        'и', 'в', 'во', 'не', 'что', 'он', 'на', 'я', 'с', 'со', 'как', 'а', 'то',
        'все', 'она', 'так', 'его', 'но', 'да', 'ты', 'к', 'у', 'же', 'вы', 'за',
        'бы', 'по', 'только', 'ее', 'мне', 'от', 'о', 'из', 'ему', 'для',
    ];
}
