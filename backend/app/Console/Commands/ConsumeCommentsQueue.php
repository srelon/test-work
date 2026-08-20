<?php

namespace App\Console\Commands;

use App\Services\CommentService;
use App\Services\RabbitMQService;
use App\Services\Resilience\ReliableQueue;
use Illuminate\Console\Command;
use Throwable;

class ConsumeCommentsQueue extends Command
{
    protected $signature = 'comments:consume';

    protected $description = 'Consume the comments_create RabbitMQ queue and persist comments';

    public function handle(RabbitMQService $rabbitMQService, CommentService $commentService, ReliableQueue $reliableQueue): int {
        $queue = config('rabbitmq.queues.comments_create');

        $this->info("Waiting for messages on {$queue} ...");

        $rabbitMQService->consume($queue, function (array $payload) use ($commentService, $reliableQueue, $queue) {
            try {
                $commentService->persist($payload);
            } catch (Throwable $e) {
                report($e);

                $reliableQueue->runFallbackJob($queue, $payload);
            }
        });

        return self::SUCCESS;
    }
}
