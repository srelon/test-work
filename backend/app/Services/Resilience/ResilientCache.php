<?php

namespace App\Services\Resilience;

use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ResilientCache
{
    private const SERVICE = 'redis';

    public function __construct(private ServiceFallback $fallback) {}

    public function remember(string $key, int $ttl, Closure $callback, array $tags = []): mixed {
        return $this->fallback->attempt(
            self::SERVICE,
            function () use ($key, $ttl, $callback, $tags) {
                $store = Cache::store('redis');
                $store = $tags ? $store->tags($tags) : $store;

                return $store->remember($key, $ttl, $callback);
            },
            function () use ($key, $ttl, $callback, $tags) {
                if ($tags) {
                    return $callback();
                }

                try {
                    return Cache::store('database')->remember($key, $ttl, $callback);
                } catch (Throwable $e) {
                    report($e);

                    return $callback();
                }
            },
        );
    }

    public function many(array $keys): array {
        return $this->fallback->attempt(
            self::SERVICE,
            fn () => Cache::store('redis')->many($keys),
            function () use ($keys) {
                try {
                    return Cache::store('database')->many($keys);
                } catch (Throwable $e) {
                    report($e);

                    return array_fill_keys($keys, null);
                }
            },
        );
    }

    public function forever(string $key, mixed $value): void {
        $this->fallback->attempt(
            self::SERVICE,
            fn () => Cache::store('redis')->forever($key, $value),
            function () use ($key, $value) {
                try {
                    Cache::store('database')->forever($key, $value);
                } catch (Throwable $e) {
                    report($e);
                }
            },
        );
    }

    public function forget(string $key): void {
        $this->fallback->attempt(
            self::SERVICE,
            fn () => Cache::store('redis')->forget($key),
            function () use ($key) {
                try {
                    Cache::store('database')->forget($key);
                } catch (Throwable $e) {
                    report($e);
                }
            },
        );
    }

    public function flushTags(array $tags): void {
        $this->fallback->attempt(
            self::SERVICE,
            fn () => Cache::store('redis')->tags($tags)->flush(),
            fn () => null,
        );
    }
}
