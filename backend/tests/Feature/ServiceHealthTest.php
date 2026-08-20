<?php

namespace Tests\Feature;

use App\Services\Resilience\ServiceHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ServiceHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_down_backs_off_progressively_on_repeated_failures(): void {
        $health = app(ServiceHealth::class);

        $expirations = [];

        foreach (range(1, 4) as $attempt) {
            $health->markDown('redis');
            $expirations[] = DB::table('cache')->where('key', 'like', '%resilient_cache:redis_down')->value('expiration');
        }

        $this->assertLessThan($expirations[1], $expirations[0]);
        $this->assertLessThan($expirations[2], $expirations[1]);
        $this->assertLessThan($expirations[3], $expirations[2]);
    }

    public function test_mark_up_resets_the_backoff(): void {
        $health = app(ServiceHealth::class);

        $health->markDown('redis');
        $health->markDown('redis');
        $health->markUp('redis');
        $health->markDown('redis');

        $afterReset = DB::table('cache')->where('key', 'like', '%resilient_cache:redis_down')->value('expiration');

        $health->markUp('redis');
        $health->markDown('redis');

        $freshAttempt = DB::table('cache')->where('key', 'like', '%resilient_cache:redis_down')->value('expiration');

        $this->assertSame($afterReset, $freshAttempt);
    }
}
