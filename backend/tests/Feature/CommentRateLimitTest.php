<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\FakesRabbitMQService;
use Tests\Feature\Concerns\FakesRecaptcha;
use Tests\TestCase;

class CommentRateLimitTest extends TestCase
{
    use FakesRabbitMQService;
    use FakesRecaptcha;
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();

        $this->fakeRabbitMQService();
        $this->fakeRecaptcha();
    }

    public function test_store_is_rate_limited_after_ten_requests_per_minute(): void {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/comments', [
                'user_name' => 'RateTest',
                'email' => 'rate@example.com',
                'text' => '<p>x</p>',
                'recaptcha_token' => 'test-token',
            ])->assertStatus(202);
        }

        $response = $this->postJson('/api/comments', [
            'user_name' => 'RateTest',
            'email' => 'rate@example.com',
            'text' => '<p>x</p>',
            'recaptcha_token' => 'test-token',
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
