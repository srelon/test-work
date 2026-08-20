<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Services\CommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CommentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_persist_creates_a_top_level_comment(): void {
        app(CommentService::class)->persist([
            'user_name' => 'JaneDoe',
            'email' => 'jane@example.com',
            'home_page' => 'https://example.com',
            'text' => '<p>Hello world</p>',
        ]);

        $this->assertDatabaseHas('comments', [
            'user_name' => 'JaneDoe',
            'email' => 'jane@example.com',
            'home_page' => 'https://example.com',
        ]);
    }

    public function test_persist_creates_a_reply_with_replied_to_comment_id(): void {
        $parent = Comment::create(['user_name' => 'Jane Doe', 'email' => 'jane@example.com', 'body' => '<p>Top level</p>']);
        $first_reply = Comment::create(['parent_id' => $parent->id, 'user_name' => 'John Smith', 'email' => 'john@example.com', 'body' => '<p>First reply</p>']);

        app(CommentService::class)->persist([
            'parent_id' => $parent->id,
            'replied_to_comment_id' => $first_reply->id,
            'user_name' => 'MariaLane',
            'email' => 'maria@example.com',
            'text' => '<p>Replying to John</p>',
        ]);

        $this->assertDatabaseHas('comments', [
            'parent_id' => $parent->id,
            'replied_to_comment_id' => $first_reply->id,
            'user_name' => 'MariaLane',
        ]);
    }

    public function test_persist_and_database_treat_sql_injection_payloads_as_literal_data(): void {
        $payloads = [
            "Robert'); DROP TABLE comments; --",
            "' OR '1'='1",
            "' UNION SELECT * FROM comments --",
        ];

        $service = app(CommentService::class);

        foreach ($payloads as $payload) {
            $service->persist([
                'user_name' => 'SqliTester',
                'email' => 'sqli@example.com',
                'text' => $service->sanitizeBody('<p>'.$payload.'</p>'),
            ]);

            $this->assertDatabaseHas('comments', ['body' => $payload]);
        }

        $this->assertTrue(Schema::hasTable('comments'));
        $this->assertSame(count($payloads), Comment::count());
    }

    public function test_persist_saves_base64_images_to_public_storage(): void {
        Storage::fake('public');

        $pixel = 'data:image/png;base64,'.base64_encode($this->fakePngBytes());

        app(CommentService::class)->persist([
            'user_name' => 'ImagePoster',
            'email' => 'img@example.com',
            'text' => '<p>with image</p>',
            'image' => ['original' => $pixel, 'cropped' => $pixel],
        ]);

        $comment = Comment::latest('id')->first();
        $this->assertStringEndsWith('.png', $comment->images['original']);
        Storage::disk('public')->assertExists($comment->images['original']);
        Storage::disk('public')->assertExists($comment->images['cropped']);

        [$cropped_width, $cropped_height] = getimagesize(Storage::disk('public')->path($comment->images['cropped']));
        $this->assertSame(320, $cropped_width);
        $this->assertSame(240, $cropped_height);
    }

    public function test_persist_downscales_the_original_image_to_fit_full_hd_preserving_aspect_ratio(): void {
        Storage::fake('public');

        $large_image = 'data:image/png;base64,'.base64_encode($this->fakePngBytes(2400, 1600));
        $small_image = 'data:image/png;base64,'.base64_encode($this->fakePngBytes(2, 2));

        app(CommentService::class)->persist([
            'user_name' => 'ImagePoster',
            'email' => 'img@example.com',
            'text' => '<p>with image</p>',
            'image' => ['original' => $large_image, 'cropped' => $small_image],
        ]);

        $comment = Comment::latest('id')->first();
        [$width, $height] = getimagesize(Storage::disk('public')->path($comment->images['original']));

        $this->assertLessThanOrEqual(1920, $width);
        $this->assertLessThanOrEqual(1080, $height);
        $this->assertSame(round(2400 / 1600, 2), round($width / $height, 2));
    }

    public function test_persist_rejects_non_image_data_disguised_as_an_image(): void {
        Storage::fake('public');

        $fake_image = 'data:image/png;base64,'.base64_encode('this is definitely not image data');

        $this->expectException(ValidationException::class);

        try {
            app(CommentService::class)->persist([
                'user_name' => 'Attacker',
                'email' => 'attacker@example.com',
                'text' => '<p>with fake image</p>',
                'image' => ['original' => $fake_image, 'cropped' => $fake_image],
            ]);
        } finally {
            $this->assertSame(0, Comment::count());
            Storage::disk('public')->assertDirectoryEmpty('comments');
        }
    }

    public function test_persist_logs_a_warning_when_image_processing_fails(): void {
        Storage::fake('public');
        Log::spy();

        $fake_image = 'data:image/png;base64,'.base64_encode('this is definitely not image data');

        try {
            app(CommentService::class)->persist([
                'user_name' => 'Attacker',
                'email' => 'attacker@example.com',
                'text' => '<p>with fake image</p>',
                'image' => ['original' => $fake_image, 'cropped' => $fake_image],
            ]);
        } catch (ValidationException) {
            // expected — asserted by test_persist_rejects_non_image_data_disguised_as_an_image
        }

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message) => $message === 'Comment image processing failed, comment was not persisted.');
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
