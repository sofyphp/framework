<?php

declare(strict_types=1);

namespace Tests\Unit;

use Sofy\View\UI\Page;
use Tests\TestCase;

final class UiCompilerTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure a clean, manifest-free state regardless of prior `ui:build`.
        $manifest = dirname(__DIR__, 2) . '/bootstrap/cache/ui-manifest.php';
        if (is_file($manifest)) {
            @unlink($manifest);
        }
        Page::flushAssetCache();
    }

    public function test_css_source_is_non_empty_and_tagless(): void
    {
        $css = Page::cssSource();
        $this->assertNotEmpty($css);
        $this->assertStringNotContainsString('<style>', $css);
        $this->assertStringContainsString('--accent', $css);
    }

    public function test_js_source_has_no_script_tags(): void
    {
        $js = Page::jsSource();
        $this->assertNotEmpty($js);
        $this->assertStringStartsNotWith('<script', trim($js));
        $this->assertStringNotContainsString('</script>', $js);
    }

    public function test_inline_by_default_without_manifest(): void
    {
        $this->assertNull(Page::compiledAssets());
    }

    public function test_page_inlines_css_when_not_compiled(): void
    {
        $html = (new Page('T'))->render();
        $this->assertStringContainsString('<style>', $html);
        $this->assertStringNotContainsString('/assets/sofy.', $html);
    }
}
