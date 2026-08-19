<?php

namespace Tests\Feature;

use App\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_top_level_comments_with_replies_count_only(): void {
        $parent = Comment::create([
            'user_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'body' => '<p>Top level</p>',
        ]);

        Comment::create([
            'parent_id' => $parent->id,
            'user_name' => 'John Smith',
            'email' => 'john@example.com',
            'body' => '<p>A reply</p>',
        ]);

        $response = $this->getJson('/api/comments');

        $response->assertStatus(200);
        $response->assertJsonPath('data.items.data.0.id', $parent->id);
        $response->assertJsonPath('data.items.data.0.replies_count', 1);
        $response->assertJsonMissingPath('data.items.data.0.replies');
    }

    public function test_replies_returns_child_comments_for_a_top_level_comment(): void {
        $parent = Comment::create([
            'user_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'body' => '<p>Top level</p>',
        ]);

        $reply = Comment::create([
            'parent_id' => $parent->id,
            'user_name' => 'John Smith',
            'email' => 'john@example.com',
            'body' => '<p>A reply</p>',
        ]);

        $response = $this->getJson("/api/comments/{$parent->id}/replies");

        $response->assertStatus(200);
        $response->assertJsonPath('data.replies.0.id', $reply->id);
        $response->assertJsonPath('data.replies.0.user_name', 'John Smith');
    }

    public function test_replies_rejects_a_reply_id(): void {
        $parent = Comment::create([
            'user_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'body' => '<p>Top level</p>',
        ]);

        $reply = Comment::create([
            'parent_id' => $parent->id,
            'user_name' => 'John Smith',
            'email' => 'john@example.com',
            'body' => '<p>A reply</p>',
        ]);

        $response = $this->getJson("/api/comments/{$reply->id}/replies");

        $response->assertStatus(404);
    }

    public function test_index_orders_by_requested_sort(): void {
        Comment::create(['user_name' => 'Bravo', 'email' => 'bravo@example.com', 'body' => '<p>b</p>']);
        Comment::create(['user_name' => 'Alpha', 'email' => 'alpha@example.com', 'body' => '<p>a</p>']);

        $response = $this->getJson('/api/comments?sort_by=user_name_asc');

        $response->assertStatus(200);
        $response->assertJsonPath('data.items.data.0.user_name', 'Alpha');
        $response->assertJsonPath('data.items.data.1.user_name', 'Bravo');
    }

    public function test_index_rejects_invalid_sort_by(): void {
        $response = $this->getJson('/api/comments?sort_by=bogus');

        $response->assertStatus(422);
    }

    public function test_index_rejects_sql_injection_attempt_via_sort_by(): void {
        $response = $this->getJson('/api/comments?'.http_build_query([
            'sort_by' => 'created_at; DROP TABLE comments; --',
        ]));

        $response->assertStatus(422);
        $this->assertTrue(Schema::hasTable('comments'));
    }

    public function test_index_reflects_a_new_comment_after_the_page_was_already_cached(): void {
        Comment::create(['user_name' => 'Jane Doe', 'email' => 'jane@example.com', 'body' => '<p>First</p>']);

        $this->getJson('/api/comments')->assertJsonCount(1, 'data.items.data');

        $this->postJson('/api/comments', [
            'user_name' => 'JohnDoe',
            'email' => 'john@example.com',
            'text' => '<p>Second</p>',
        ])->assertStatus(201);

        $this->getJson('/api/comments')->assertJsonCount(2, 'data.items.data');
    }

    public function test_index_reflects_updated_replies_count_after_the_page_was_already_cached(): void {
        $parent = Comment::create(['user_name' => 'Jane Doe', 'email' => 'jane@example.com', 'body' => '<p>Top level</p>']);

        $this->getJson('/api/comments')->assertJsonPath('data.items.data.0.replies_count', 0);

        $this->postJson('/api/comments', [
            'parent_id' => $parent->id,
            'user_name' => 'JohnDoe',
            'email' => 'john@example.com',
            'text' => '<p>A reply</p>',
        ])->assertStatus(201);

        $this->getJson('/api/comments')->assertJsonPath('data.items.data.0.replies_count', 1);
    }

    public function test_replies_reflects_a_new_reply_after_it_was_already_cached(): void {
        $parent = Comment::create(['user_name' => 'Jane Doe', 'email' => 'jane@example.com', 'body' => '<p>Top level</p>']);
        Comment::create(['parent_id' => $parent->id, 'user_name' => 'John Smith', 'email' => 'john@example.com', 'body' => '<p>First reply</p>']);

        $this->getJson("/api/comments/{$parent->id}/replies")->assertJsonCount(1, 'data.replies');

        $this->postJson('/api/comments', [
            'parent_id' => $parent->id,
            'user_name' => 'MariaLane',
            'email' => 'maria@example.com',
            'text' => '<p>Second reply</p>',
        ])->assertStatus(201);

        $this->getJson("/api/comments/{$parent->id}/replies")->assertJsonCount(2, 'data.replies');
    }

    public function test_store_creates_a_top_level_comment(): void {
        $response = $this->postJson('/api/comments', [
            'user_name' => 'JaneDoe',
            'email' => 'jane@example.com',
            'home_page' => 'https://example.com',
            'text' => '<p>Hello world</p>',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('comments', [
            'user_name' => 'JaneDoe',
            'email' => 'jane@example.com',
            'home_page' => 'https://example.com',
        ]);
    }

    public function test_store_creates_a_reply_with_replied_to_comment_id(): void {
        $parent = Comment::create(['user_name' => 'Jane Doe', 'email' => 'jane@example.com', 'body' => '<p>Top level</p>']);
        $first_reply = Comment::create(['parent_id' => $parent->id, 'user_name' => 'John Smith', 'email' => 'john@example.com', 'body' => '<p>First reply</p>']);

        $response = $this->postJson('/api/comments', [
            'parent_id' => $parent->id,
            'replied_to_comment_id' => $first_reply->id,
            'user_name' => 'MariaLane',
            'email' => 'maria@example.com',
            'text' => '<p>Replying to John</p>',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.replied_to.id', $first_reply->id);
        $this->assertDatabaseHas('comments', [
            'parent_id' => $parent->id,
            'replied_to_comment_id' => $first_reply->id,
            'user_name' => 'MariaLane',
        ]);
    }

    public function test_store_rejects_replied_to_comment_id_from_a_different_thread(): void {
        $parent = Comment::create(['user_name' => 'Jane Doe', 'email' => 'jane@example.com', 'body' => '<p>Top level</p>']);
        $other_parent = Comment::create(['user_name' => 'Alex Roe', 'email' => 'alex@example.com', 'body' => '<p>Other thread</p>']);
        $reply_in_other_thread = Comment::create(['parent_id' => $other_parent->id, 'user_name' => 'Bob', 'email' => 'bob@example.com', 'body' => '<p>Reply</p>']);

        $response = $this->postJson('/api/comments', [
            'parent_id' => $parent->id,
            'replied_to_comment_id' => $reply_in_other_thread->id,
            'user_name' => 'BadActor',
            'email' => 'bad@example.com',
            'text' => '<p>should fail</p>',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('replied_to_comment_id');
    }

    public function test_store_rejects_parent_id_that_is_not_a_top_level_comment(): void {
        $parent = Comment::create(['user_name' => 'Jane Doe', 'email' => 'jane@example.com', 'body' => '<p>Top level</p>']);
        $reply = Comment::create(['parent_id' => $parent->id, 'user_name' => 'John Smith', 'email' => 'john@example.com', 'body' => '<p>Reply</p>']);

        $response = $this->postJson('/api/comments', [
            'parent_id' => $reply->id,
            'user_name' => 'BadActor',
            'email' => 'bad@example.com',
            'text' => '<p>should fail</p>',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('parent_id');
    }

    public function test_store_requires_user_name_email_and_text(): void {
        $response = $this->postJson('/api/comments', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_name', 'email', 'text']);
    }

    public function test_store_rejects_user_name_with_special_characters(): void {
        $response = $this->postJson('/api/comments', [
            'user_name' => 'John Doe!',
            'email' => 'john@example.com',
            'text' => '<p>Hello world</p>',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('user_name');
    }

    public function test_store_accepts_user_name_with_only_letters_and_digits(): void {
        $response = $this->postJson('/api/comments', [
            'user_name' => 'JohnDoe123',
            'email' => 'john@example.com',
            'text' => '<p>Hello world</p>',
        ]);

        $response->assertStatus(201);
    }

    public function test_store_rejects_text_over_1000_characters(): void {
        $response = $this->postJson('/api/comments', [
            'user_name' => 'LongText',
            'email' => 'long@example.com',
            'text' => '<p>'.str_repeat('a', 1001).'</p>',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('text');
    }

    public function test_store_accepts_text_at_exactly_1000_characters(): void {
        $response = $this->postJson('/api/comments', [
            'user_name' => 'ExactLength',
            'email' => 'exact@example.com',
            'text' => '<p>'.str_repeat('a', 1000).'</p>',
        ]);

        $response->assertStatus(201);
    }

    public function test_store_strips_script_tags_and_event_handler_attributes_from_body(): void {
        $response = $this->postJson('/api/comments', [
            'user_name' => 'XSSTester',
            'email' => 'xss@example.com',
            'text' => '<p onclick="alert(1)">Hello <script>alert(document.cookie)</script>'
                .'<img src=x onerror="alert(1)"><strong style="color:red" class="x">world</strong></p>',
        ]);

        $response->assertStatus(201);
        $body = $response->json('data.text');

        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('onerror', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('<img', $body);
        $this->assertStringNotContainsString('style=', $body);
        $this->assertStringNotContainsString('class=', $body);
        $this->assertStringContainsString('<strong>world</strong>', $body);
    }

    public function test_store_strips_javascript_protocol_and_disallowed_attributes_from_links(): void {
        $response = $this->postJson('/api/comments', [
            'user_name' => 'XSSTester',
            'email' => 'xss@example.com',
            'text' => '<p><a href="javascript:alert(document.cookie)" onclick="steal()" target="_blank" title="ok">click</a></p>',
        ]);

        $response->assertStatus(201);
        $body = $response->json('data.text');

        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('target=', $body);
        $this->assertStringContainsString('<a title="ok" rel="nofollow noindex">click</a>', $body);
    }

    public function test_store_keeps_safe_links_with_only_href_and_title(): void {
        $response = $this->postJson('/api/comments', [
            'user_name' => 'SafeLink',
            'email' => 'safe@example.com',
            'text' => '<p>Check <a href="https://example.com" title="Example">this</a> out.</p>',
        ]);

        $response->assertStatus(201);
        $this->assertStringContainsString('<a href="https://example.com" title="Example" rel="nofollow noindex">this</a>', $response->json('data.text'));
    }

    public function test_store_preserves_line_breaks_between_paragraphs(): void {
        $response = $this->postJson('/api/comments', [
            'user_name' => 'Multiline',
            'email' => 'multiline@example.com',
            'text' => '<p>First line</p><p>Second line</p><p>Third line<br>fourth line</p>',
        ]);

        $response->assertStatus(201);
        $this->assertSame("First line\nSecond line\nThird line\nfourth line", $response->json('data.text'));
    }

    public function test_store_keeps_a_code_block_with_its_internal_spaces_intact(): void {
        $response = $this->postJson('/api/comments', [
            'user_name' => 'CodePoster',
            'email' => 'code@example.com',
            'text' => '<code>function foo() {
    return    1;
}</code>',
        ]);

        $response->assertStatus(201);
        $this->assertSame("<code>function foo() {\n    return    1;\n}</code>", $response->json('data.text'));
    }

    public function test_store_adds_nofollow_noindex_to_every_link_in_the_body(): void {
        $response = $this->postJson('/api/comments', [
            'user_name' => 'LinkPoster',
            'email' => 'link@example.com',
            'text' => '<p><a href="https://example.com">bare link</a></p>',
        ]);

        $response->assertStatus(201);
        $this->assertStringContainsString('rel="nofollow noindex"', $response->json('data.text'));
    }

    public function test_store_and_database_treat_sql_injection_payloads_as_literal_data(): void {
        $payloads = [
            "Robert'); DROP TABLE comments; --",
            "' OR '1'='1",
            "' UNION SELECT * FROM comments --",
        ];

        foreach ($payloads as $payload) {
            $response = $this->postJson('/api/comments', [
                'user_name' => 'SqliTester',
                'email' => 'sqli@example.com',
                'text' => '<p>'.$payload.'</p>',
            ]);

            $response->assertStatus(201);
            $this->assertDatabaseHas('comments', ['body' => $payload]);
        }

        $this->assertTrue(Schema::hasTable('comments'));
        $this->assertSame(count($payloads), Comment::count());
    }

    public function test_store_rejects_non_integer_parent_id_and_replied_to_comment_id(): void {
        $response = $this->postJson('/api/comments', [
            'parent_id' => '1 OR 1=1',
            'replied_to_comment_id' => '1; DROP TABLE comments; --',
            'user_name' => 'BadActor',
            'email' => 'bad@example.com',
            'text' => '<p>should fail</p>',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['parent_id', 'replied_to_comment_id']);
        $this->assertTrue(Schema::hasTable('comments'));
    }

    public function test_store_saves_base64_images_to_public_storage(): void {
        Storage::fake('public');

        $pixel = 'data:image/png;base64,'.base64_encode($this->fakePngBytes());

        $response = $this->postJson('/api/comments', [
            'user_name' => 'ImagePoster',
            'email' => 'img@example.com',
            'text' => '<p>with image</p>',
            'image' => ['original' => $pixel, 'cropped' => $pixel],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.image.original', fn ($url) => str_contains($url, '/storage/comments/'));

        $comment = Comment::latest('id')->first();
        $this->assertStringEndsWith('.png', $comment->image_original);
        Storage::disk('public')->assertExists($comment->image_original);
        Storage::disk('public')->assertExists($comment->image_cropped);

        [$cropped_width, $cropped_height] = getimagesize(Storage::disk('public')->path($comment->image_cropped));
        $this->assertSame(320, $cropped_width);
        $this->assertSame(240, $cropped_height);
    }

    public function test_store_downscales_the_original_image_to_fit_full_hd_preserving_aspect_ratio(): void {
        Storage::fake('public');

        $large_image = 'data:image/png;base64,'.base64_encode($this->fakePngBytes(2400, 1600));
        $small_image = 'data:image/png;base64,'.base64_encode($this->fakePngBytes(2, 2));

        $response = $this->postJson('/api/comments', [
            'user_name' => 'ImagePoster',
            'email' => 'img@example.com',
            'text' => '<p>with image</p>',
            'image' => ['original' => $large_image, 'cropped' => $small_image],
        ]);

        $response->assertStatus(201);

        $comment = Comment::latest('id')->first();
        [$width, $height] = getimagesize(Storage::disk('public')->path($comment->image_original));

        $this->assertLessThanOrEqual(1920, $width);
        $this->assertLessThanOrEqual(1080, $height);
        $this->assertSame(round(2400 / 1600, 2), round($width / $height, 2));
    }

    public function test_store_rejects_non_image_data_disguised_as_an_image(): void {
        Storage::fake('public');

        $fake_image = 'data:image/png;base64,'.base64_encode('this is definitely not image data');

        $response = $this->postJson('/api/comments', [
            'user_name' => 'Attacker',
            'email' => 'attacker@example.com',
            'text' => '<p>with fake image</p>',
            'image' => ['original' => $fake_image, 'cropped' => $fake_image],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('image');
        Storage::disk('public')->assertDirectoryEmpty('comments');
    }

    protected function fakePngBytes(int $width = 2, int $height = 2): string {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
