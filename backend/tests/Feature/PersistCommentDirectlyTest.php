<?php

namespace Tests\Feature;

use App\Jobs\PersistCommentDirectly;
use App\Services\CommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersistCommentDirectlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_persists_the_comment(): void {
        (new PersistCommentDirectly(['user_name' => 'JaneDoe', 'email' => 'jane@example.com', 'text' => '<p>hi</p>']))
            ->handle(app(CommentService::class));

        $this->assertDatabaseHas('comments', ['user_name' => 'JaneDoe', 'email' => 'jane@example.com']);
    }

    public function test_job_is_configured_to_retry_with_backoff(): void {
        $job = new PersistCommentDirectly(['user_name' => 'JaneDoe']);

        $this->assertSame(5, $job->tries);
        $this->assertSame([10, 30, 60, 120, 300], $job->backoff());
    }
}
