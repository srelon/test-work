<?php

namespace Tests\Feature;

use App\Services\CommentService;
use Tests\TestCase;

class CommentSanitizeBodyTest extends TestCase
{
    public function test_sanitize_body_strips_script_tags_and_event_handler_attributes(): void {
        $body = app(CommentService::class)->sanitizeBody(
            '<p onclick="alert(1)">Hello <script>alert(document.cookie)</script>'
            .'<img src=x onerror="alert(1)"><strong style="color:red" class="x">world</strong></p>',
        );

        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('onerror', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('<img', $body);
        $this->assertStringNotContainsString('style=', $body);
        $this->assertStringNotContainsString('class=', $body);
        $this->assertStringContainsString('<strong>world</strong>', $body);
    }

    public function test_sanitize_body_strips_javascript_protocol_and_disallowed_attributes_from_links(): void {
        $body = app(CommentService::class)->sanitizeBody(
            '<p><a href="javascript:alert(document.cookie)" onclick="steal()" target="_blank" title="ok">click</a></p>',
        );

        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('target=', $body);
        $this->assertStringContainsString('<a title="ok" rel="nofollow noindex">click</a>', $body);
    }

    public function test_sanitize_body_keeps_safe_links_with_only_href_and_title(): void {
        $body = app(CommentService::class)->sanitizeBody('<p>Check <a href="https://example.com" title="Example">this</a> out.</p>');

        $this->assertStringContainsString('<a href="https://example.com" title="Example" rel="nofollow noindex">this</a>', $body);
    }

    public function test_sanitize_body_preserves_line_breaks_between_paragraphs(): void {
        $body = app(CommentService::class)->sanitizeBody('<p>First line</p><p>Second line</p><p>Third line<br>fourth line</p>');

        $this->assertSame("First line\nSecond line\nThird line\nfourth line", $body);
    }

    public function test_sanitize_body_keeps_a_code_block_with_its_internal_spaces_intact(): void {
        $body = app(CommentService::class)->sanitizeBody('<code>function foo() {
    return    1;
}</code>');

        $this->assertSame("<code>function foo() {\n    return    1;\n}</code>", $body);
    }

    public function test_sanitize_body_adds_nofollow_noindex_to_every_link(): void {
        $body = app(CommentService::class)->sanitizeBody('<p><a href="https://example.com">bare link</a></p>');

        $this->assertStringContainsString('rel="nofollow noindex"', $body);
    }
}
