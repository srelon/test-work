<?php

namespace App\Services\Resilience;

use Illuminate\Support\Facades\Cache;
use Throwable;

class ServiceHealth
{
    private const BACKOFF = [10, 30, 60, 120, 300];

    private const ATTEMPTS_TTL = 600;

    public function shouldTry(string $service): bool {
        try {
            return ! Cache::store('database')->get($this->downFlagKey($service), false);
        } catch (Throwable $e) {
            report($e);

            return true;
        }
    }

    public function markDown(string $service): void {
        try {
            $store = Cache::store('database');

            $attempt = (int) $store->get($this->attemptsKey($service), 0);
            $seconds = self::BACKOFF[min($attempt, count(self::BACKOFF) - 1)];

            $store->put($this->downFlagKey($service), true, $seconds);
            $store->put($this->attemptsKey($service), $attempt + 1, self::ATTEMPTS_TTL);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function markUp(string $service): void {
        try {
            $store = Cache::store('database');

            $store->forget($this->downFlagKey($service));
            $store->forget($this->attemptsKey($service));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function downFlagKey(string $service): string {
        return "resilient_cache:{$service}_down";
    }

    private function attemptsKey(string $service): string {
        return "resilient_cache:{$service}_attempts";
    }
}
