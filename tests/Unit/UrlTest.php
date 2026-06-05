<?php

declare(strict_types=1);

namespace Tests\Unit;

use Sofy\Support\Url;
use Tests\TestCase;

/** Security helper: safe post-login redirects (open-redirect defense). */
final class UrlTest extends TestCase
{
    public function test_relative_paths_pass_through(): void
    {
        $this->assertSame('/admin', Url::sameOrigin('/admin', '/fallback'));
        $this->assertSame('/admin?x=1', Url::sameOrigin('/admin?x=1', '/fallback'));
    }

    public function test_empty_or_null_returns_fallback(): void
    {
        $this->assertSame('/fb', Url::sameOrigin(null, '/fb'));
        $this->assertSame('/fb', Url::sameOrigin('', '/fb'));
    }

    public function test_protocol_relative_is_rejected(): void
    {
        $this->assertSame('/fb', Url::sameOrigin('//evil.com/x', '/fb'));
    }

    public function test_cross_origin_absolute_is_rejected(): void
    {
        $this->assertSame('/fb', Url::sameOrigin('https://evil.com/x', '/fb'));
    }

    public function test_bad_scheme_is_rejected(): void
    {
        $this->assertSame('/fb', Url::sameOrigin('javascript:alert(1)', '/fb'));
    }

    public function test_same_origin_absolute_is_kept(): void
    {
        // APP_URL is http://localhost in the test bootstrap.
        $this->assertSame('http://localhost/admin', Url::sameOrigin('http://localhost/admin', '/fb'));
    }
}
