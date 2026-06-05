<?php

declare(strict_types=1);

namespace Tests\Unit;

use Sofy\View\UI;
use Tests\TestCase;

/** Per-instance ->color() + its injection-safe sanitizer. */
final class ComponentColorTest extends TestCase
{
    public function test_valid_colors_emit_c_variable(): void
    {
        $this->assertStringContainsString('--c:#7c5cff', (string) UI::badge('x')->color('#7c5cff'));
        $this->assertStringContainsString('--c:tomato', (string) UI::badge('x')->color('tomato'));
        $this->assertStringContainsString('--c:rgb(10,20,30)', (string) UI::button('x', '#')->color('rgb(10,20,30)'));
        $this->assertStringContainsString('--c:var(--accent2)', (string) UI::progress(50)->color('var(--accent2)'));
        $this->assertStringContainsString('--c:teal', (string) UI::tag('x')->color('teal'));
    }

    public function test_no_color_emits_no_style(): void
    {
        $this->assertStringNotContainsString('--c', (string) UI::badge('plain'));
    }

    public function test_css_injection_is_rejected(): void
    {
        $html = (string) UI::badge('x')->color('red;} body{display:none}');
        $this->assertStringNotContainsString('--c', $html);
        $this->assertStringNotContainsString('display:none', $html);
    }

    public function test_xss_is_rejected(): void
    {
        $html = (string) UI::badge('x')->color('"><script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('--c', $html);
    }
}
