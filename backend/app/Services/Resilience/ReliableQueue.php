<?php

namespace App\Services\Resilience;

use App\Services\RabbitMQService;
use RuntimeException;

class ReliableQueue
{
    public function __construct(
        private RabbitMQService $rabbitMQService,
        private ServiceFallback $fallback,
    ) {}

    public function send(string $queue, array $data): void {
        $this->fallback->attempt(
            'rabbitmq',
            fn () => $this->rabbitMQService->publish($queue, $data),
            fn () => $this->runFallbackJob($queue, $data),
        );
    }

    public function runFallbackJob(string $queue, array $data): void {
        $jobClass = config("reliable_queue.fallback_jobs.{$queue}");

        if ($jobClass === null) {
            throw new RuntimeException("No fallback job configured for queue [{$queue}] — add it to config('reliable_queue.fallback_jobs').");
        }

        $this->fallback->attempt(
            'redis',
            function () use ($jobClass, $data) {
                $jobClass::dispatch($data);
            },
            fn () => app()->call([new $jobClass($data), 'handle']),
        );
    }
}
