<?php

namespace Tests\Feature;

use App\Services\Resilience\ServiceFallback;
use App\Services\Resilience\ServiceHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ServiceFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_primary_result_when_it_succeeds(): void {
        $fallbackRan = false;

        $result = app(ServiceFallback::class)->attempt(
            'redis',
            fn () => 'primary',
            function () use (&$fallbackRan) {
                $fallbackRan = true;

                return 'fallback';
            },
        );

        $this->assertSame('primary', $result);
        $this->assertFalse($fallbackRan);
    }

    public function test_returns_the_fallback_result_when_the_primary_closure_throws(): void {
        $result = app(ServiceFallback::class)->attempt(
            'redis',
            function () {
                throw new RuntimeException('redis unavailable');
            },
            fn () => 'fallback',
        );

        $this->assertSame('fallback', $result);
    }

    public function test_skips_the_primary_closure_entirely_once_the_circuit_is_open(): void {
        app(ServiceHealth::class)->markDown('redis');

        $primaryAttempted = false;

        $result = app(ServiceFallback::class)->attempt(
            'redis',
            function () use (&$primaryAttempted) {
                $primaryAttempted = true;

                return 'primary';
            },
            fn () => 'fallback',
        );

        $this->assertFalse($primaryAttempted);
        $this->assertSame('fallback', $result);
    }

    public function test_tracks_each_service_independently(): void {
        app(ServiceHealth::class)->markDown('rabbitmq');

        $redisAttempted = false;
        $rabbitmqAttempted = false;

        app(ServiceFallback::class)->attempt('redis', function () use (&$redisAttempted) {
            $redisAttempted = true;
        }, fn () => null);

        app(ServiceFallback::class)->attempt('rabbitmq', function () use (&$rabbitmqAttempted) {
            $rabbitmqAttempted = true;
        }, fn () => null);

        $this->assertTrue($redisAttempted);
        $this->assertFalse($rabbitmqAttempted);
    }
}
