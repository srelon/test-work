<?php

namespace Tests\Feature;

use App\Jobs\PersistCommentDirectly;
use App\Services\RabbitMQService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PhpAmqpLib\Exception\AMQPIOException;
use Tests\Feature\Concerns\FakesRabbitMQService;
use Tests\TestCase;

class CommentQueueTest extends TestCase
{
    use RefreshDatabase;
    use FakesRabbitMQService;

    protected function setUp(): void {
        parent::setUp();

        $this->fakeRabbitMQService();
    }

    public function test_store_publishes_to_rabbitmq_and_returns_202(): void {
        $fake = $this->fakeRabbitMQService();

        $response = $this->postJson('/api/comments', [
            'user_name' => 'JaneDoe',
            'email' => 'jane@example.com',
            'home_page' => 'https://example.com',
            'text' => '<p>Hello world</p>',
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('data.status', 'queued');

        $this->assertSame('JaneDoe', $fake->published['comments_create'][0]['user_name']);
        $this->assertDatabaseMissing('comments', ['user_name' => 'JaneDoe']);
    }

    public function test_store_falls_back_to_a_direct_job_when_the_broker_is_unreachable(): void {
        Queue::fake();

        $this->app->instance(RabbitMQService::class, new class extends RabbitMQService
        {
            public function publish(string $queue, array $payload): void {
                throw new AMQPIOException('Unable to connect to tcp://rabbitmq:5672');
            }
        });

        $response = $this->postJson('/api/comments', [
            'user_name' => 'JaneDoe',
            'email' => 'jane@example.com',
            'text' => '<p>Hello world</p>',
        ]);

        $response->assertStatus(202);
        Queue::assertPushed(PersistCommentDirectly::class, fn (PersistCommentDirectly $job) => $job->data['user_name'] === 'JaneDoe');
        $this->assertDatabaseMissing('comments', ['user_name' => 'JaneDoe']);
    }

    public function test_enqueue_sanitizes_text_before_publish(): void {
        $fake = $this->fakeRabbitMQService();

        $this->postJson('/api/comments', [
            'user_name' => 'XSSTester',
            'email' => 'xss@example.com',
            'text' => '<p>Hello <script>alert(1)</script>world</p>',
        ]);

        $this->assertFalse(str_contains($fake->published['comments_create'][0]['text'], '<script'));
    }
}
