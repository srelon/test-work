<?php

namespace App\Jobs;

use App\Services\CommentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PersistCommentDirectly implements ShouldQueue
{
    use Queueable;

    public $tries = 5;

    public function __construct(public array $data) {}

    public function backoff(): array {
        return [10, 30, 60, 120, 300];
    }

    public function handle(CommentService $commentService): void {
        $commentService->persist($this->data);
    }
}
