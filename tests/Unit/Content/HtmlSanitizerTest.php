<?php

namespace Tests\Unit\Content;

use App\Modules\Content\Domain\YouTubeLink;
use App\Modules\Content\Infrastructure\HtmlSanitizer;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_it_removes_scripts_handlers_and_javascript_links(): void
    {
        $clean = app(HtmlSanitizer::class)->clean(
            '<p onclick="steal()">خبر <strong>مهم</strong></p><script>alert(1)</script>'
            .'<a href="javascript:alert(1)">رابط</a><img src="x" onerror="alert(1)">'
        );

        $this->assertStringContainsString('<strong>مهم</strong>', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_it_keeps_editor_images_and_youtube_embeds_only(): void
    {
        $clean = app(HtmlSanitizer::class)->clean(
            '<figure class="image"><img src="https://adendan.com/a.jpg" alt="صورة"></figure>'
            .'<iframe src="https://www.youtube.com/embed/abc123XYZ"></iframe>'
            .'<iframe src="https://evil.example/embed"></iframe>'
        );

        $this->assertStringContainsString('<img src="https://adendan.com/a.jpg"', $clean);
        $this->assertStringContainsString('youtube.com/embed/abc123XYZ', $clean);
        $this->assertStringNotContainsString('evil.example', $clean);
    }

    public function test_youtube_links_in_every_common_format(): void
    {
        foreach (['https://youtu.be/abc123XYZ', 'https://www.youtube.com/watch?v=abc123XYZ&t=4', 'https://youtube.com/shorts/abc123XYZ'] as $url) {
            $this->assertSame('abc123XYZ', YouTubeLink::videoId($url), $url);
        }
        $this->assertNull(YouTubeLink::videoId('https://evil.example/video'));
    }
}
