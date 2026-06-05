<?php

declare(strict_types=1);

namespace Tests\Unit;

use Sofy\Search\Tokenizer;
use Tests\TestCase;

final class TokenizerTest extends TestCase
{
    private function tok(): Tokenizer
    {
        return new Tokenizer([
            'lowercase' => true, 'unaccent' => true, 'min_length' => 2, 'stopwords' => 'en', 'prefix' => true,
        ]);
    }

    public function test_lowercase_unaccent_split_and_stopwords(): void
    {
        $terms = $this->tok()->tokenize('The Quick Café Router-X1!');
        $this->assertContains('quick', $terms);
        $this->assertContains('cafe', $terms);   // accent folded
        $this->assertContains('router', $terms);
        $this->assertContains('x1', $terms);
        $this->assertNotContains('the', $terms);  // stopword dropped
    }

    public function test_min_length_drops_short_tokens(): void
    {
        $this->assertNotContains('a', $this->tok()->tokenize('a router'));
    }

    public function test_frequencies(): void
    {
        $f = $this->tok()->frequencies('router router cable');
        $this->assertSame(2, $f['router']);
        $this->assertSame(1, $f['cable']);
    }
}
