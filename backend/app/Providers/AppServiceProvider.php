<?php

namespace App\Providers;

use App\Services\RabbitMQService;
use App\Services\Resilience\ReliableQueue;
use App\Services\Resilience\ResilientCache;
use App\Services\Resilience\ServiceFallback;
use App\Services\Resilience\ServiceHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {
        $this->app->singleton(RabbitMQService::class);
        $this->app->singleton(ServiceHealth::class);
        $this->app->singleton(ServiceFallback::class);
        $this->app->singleton(ResilientCache::class);
        $this->app->singleton(ReliableQueue::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {
        if (config('database.query_log')) {
            DB::listen(fn ($query) => Log::debug($query->sql, [
                'bindings' => $query->bindings,
                'time_ms' => $query->time,
            ]));
        }
    }
}
