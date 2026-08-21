<?php

namespace Tests\Feature;

use App\Jobs\PersistCommentDirectly;
use App\Services\RabbitMQService;
use App\Services\Resilience\ReliableQueue;
use App\Services\Resilience\ServiceHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PhpAmqpLib\Exception\AMQPIOException;
use RuntimeException;
use Tests\Feature\Concerns\FakesRabbitMQService;
use Tests\TestCase;

class ReliableQueueTest extends TestCase
{
    use FakesRabbitMQService;
    use RefreshDatabase;

    public function test_send_publishes_and_never_touches_the_fallback_job(): void {
        Queue::fake();
        $fake = $this->fakeRabbitMQService();

        app(ReliableQueue::class)->send('comments_create', ['user_name' => 'JaneDoe']);

        $this->assertSame('JaneDoe', $fake->published['comments_create'][0]['user_name']);
        Queue::assertNotPushed(PersistCommentDirectly::class);
    }

    public function test_send_falls_back_to_the_configured_job_when_publish_fails(): void {
        Queue::fake();

        $this->app->instance(RabbitMQService::class, new class extends RabbitMQService {
            public function publish(string $queue, array $payload): void {
                throw new AMQPIOException('Unable to connect to tcp://rabbitmq:5672');
            }
        });

        app(ReliableQueue::class)->send('comments_create', ['user_name' => 'JaneDoe']);

        Queue::assertPushed(PersistCommentDirectly::class, fn (PersistCommentDirectly $job) => $job->data['user_name'] === 'JaneDoe');
    }

    public function test_run_fallback_job_throws_for_a_queue_with_no_configured_job(): void {
        $this->expectException(RuntimeException::class);

        app(ReliableQueue::class)->runFallbackJob('some_unconfigured_queue', ['user_name' => 'JaneDoe']);
    }

    public function test_run_fallback_job_runs_the_job_synchronously_once_the_redis_circuit_is_open(): void {
        Queue::fake();
        app(ServiceHealth::class)->markDown('redis');

        app(ReliableQueue::class)->runFallbackJob('comments_create', [
            'user_name' => 'JaneDoe',
            'email' => 'jane@example.com',
            'text' => '<p>hi</p>',
        ]);

        Queue::assertNotPushed(PersistCommentDirectly::class);
        $this->assertDatabaseHas('contacts', ['user_name' => 'JaneDoe', 'email' => 'jane@example.com']);
    }

    public function test_send_skips_the_publish_attempt_entirely_once_the_rabbitmq_circuit_is_open(): void {
        Queue::fake();
        app(ServiceHealth::class)->markDown('rabbitmq');

        $fake = $this->fakeRabbitMQService();

        app(ReliableQueue::class)->send('comments_create', ['user_name' => 'JaneDoe']);

        $this->assertSame([], $fake->published);
        Queue::assertPushed(PersistCommentDirectly::class, fn (PersistCommentDirectly $job) => $job->data['user_name'] === 'JaneDoe');
    }
}
