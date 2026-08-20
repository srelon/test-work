<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Services\CommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\FakesRabbitMQService;
use Tests\Feature\Concerns\FakesRecaptcha;
use Tests\TestCase;

class CommentControllerTest extends TestCase
{
    use RefreshDatabase;
    use FakesRabbitMQService;
    use FakesRecaptcha;

    protected function setUp(): void {
        parent::setUp();

        $this->fakeRabbitMQService();
        $this->fakeRecaptcha();
    }

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

        app(CommentService::class)->persist([
            'user_name' => 'JohnDoe',
            'email' => 'john@example.com',
            'text' => '<p>Second</p>',
        ]);

        $this->getJson('/api/comments')->assertJsonCount(2, 'data.items.data');
    }

    public function test_index_reflects_updated_replies_count_after_the_page_was_already_cached(): void {
        $parent = Comment::create(['user_name' => 'Jane Doe', 'email' => 'jane@example.com', 'body' => '<p>Top level</p>']);

        $this->getJson('/api/comments')->assertJsonPath('data.items.data.0.replies_count', 0);

        app(CommentService::class)->persist([
            'parent_id' => $parent->id,
            'user_name' => 'JohnDoe',
            'email' => 'john@example.com',
            'text' => '<p>A reply</p>',
        ]);

        $this->getJson('/api/comments')->assertJsonPath('data.items.data.0.replies_count', 1);
    }

    public function test_replies_reflects_a_new_reply_after_it_was_already_cached(): void {
        $parent = Comment::create(['user_name' => 'Jane Doe', 'email' => 'jane@example.com', 'body' => '<p>Top level</p>']);
        Comment::create(['parent_id' => $parent->id, 'user_name' => 'John Smith', 'email' => 'john@example.com', 'body' => '<p>First reply</p>']);

        $this->getJson("/api/comments/{$parent->id}/replies")->assertJsonCount(1, 'data.replies');

        app(CommentService::class)->persist([
            'parent_id' => $parent->id,
            'user_name' => 'MariaLane',
            'email' => 'maria@example.com',
            'text' => '<p>Second reply</p>',
        ]);

        $this->getJson("/api/comments/{$parent->id}/replies")->assertJsonCount(2, 'data.replies');
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
            'recaptcha_token' => 'test-token',
        ]);

        $response->assertStatus(202);
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
            'recaptcha_token' => 'test-token',
        ]);

        $response->assertStatus(202);
    }

    public function test_store_rejects_a_non_url_home_page(): void {
        $response = $this->postJson('/api/comments', [
            'user_name' => 'JohnDoe123',
            'email' => 'john@example.com',
            'home_page' => 'not-a-url',
            'text' => '<p>Hello world</p>',
            'recaptcha_token' => 'test-token',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('home_page');
    }

    public function test_store_accepts_a_valid_home_page_url(): void {
        $response = $this->postJson('/api/comments', [
            'user_name' => 'JohnDoe123',
            'email' => 'john@example.com',
            'home_page' => 'https://example.com',
            'text' => '<p>Hello world</p>',
            'recaptcha_token' => 'test-token',
        ]);

        $response->assertStatus(202);
    }

    public function test_store_rejects_image_missing_original(): void {
        $response = $this->postJson('/api/comments', [
            'user_name' => 'JohnDoe123',
            'email' => 'john@example.com',
            'text' => '<p>Hello world</p>',
            'image' => ['cropped' => 'data:image/png;base64,abc'],
            'recaptcha_token' => 'test-token',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('image.original');
    }

    public function test_store_rejects_image_missing_cropped(): void {
        $response = $this->postJson('/api/comments', [
            'user_name' => 'JohnDoe123',
            'email' => 'john@example.com',
            'text' => '<p>Hello world</p>',
            'image' => ['original' => 'data:image/png;base64,abc'],
            'recaptcha_token' => 'test-token',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('image.cropped');
    }

    public function test_store_rejects_an_svg_image(): void {
        $svg = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>');

        $response = $this->postJson('/api/comments', [
            'user_name' => 'JohnDoe123',
            'email' => 'john@example.com',
            'text' => '<p>Hello world</p>',
            'image' => ['original' => $svg, 'cropped' => $svg],
            'recaptcha_token' => 'test-token',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['image.original', 'image.cropped']);
    }

    public function test_store_accepts_a_valid_png_image(): void {
        $png = 'data:image/png;base64,'.base64_encode($this->fakePngBytes());

        $response = $this->postJson('/api/comments', [
            'user_name' => 'JohnDoe123',
            'email' => 'john@example.com',
            'text' => '<p>Hello world</p>',
            'image' => ['original' => $png, 'cropped' => $png],
            'recaptcha_token' => 'test-token',
        ]);

        $response->assertStatus(202);
    }

    public function test_store_requires_recaptcha_token(): void {
        $response = $this->postJson('/api/comments', [
            'user_name' => 'JohnDoe123',
            'email' => 'john@example.com',
            'text' => '<p>Hello world</p>',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('recaptcha_token');
    }

    public function test_store_rejects_a_failed_recaptcha_verification(): void {
        $this->fakeRecaptcha(success: false);

        $response = $this->postJson('/api/comments', [
            'user_name' => 'JohnDoe123',
            'email' => 'john@example.com',
            'text' => '<p>Hello world</p>',
            'recaptcha_token' => 'test-token',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('recaptcha_token');
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

    protected function fakePngBytes(int $width = 2, int $height = 2): string {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
