<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\FakesRabbitMQService;
use Tests\TestCase;

class CommentRateLimitTest extends TestCase
{
    use RefreshDatabase;
    use FakesRabbitMQService;

    protected function setUp(): void {
        parent::setUp();

        $this->fakeRabbitMQService();
    }

    public function test_store_is_rate_limited_after_ten_requests_per_minute(): void {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/comments', [
                'user_name' => 'RateTest',
                'email' => 'rate@example.com',
                'text' => '<p>x</p>',
            ])->assertStatus(202);
        }

        $response = $this->postJson('/api/comments', [
            'user_name' => 'RateTest',
            'email' => 'rate@example.com',
            'text' => '<p>x</p>',
        ]);

        $response->assertStatus(429);
        $response->assertJsonPath('errors', 'Too many requests, please try again later.');
    }

    public function test_index_is_rate_limited_after_sixty_requests_per_minute(): void {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/comments')->assertStatus(200);
        }

        $this->getJson('/api/comments')->assertStatus(429);
    }
}
