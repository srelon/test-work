<?php

namespace App\Events;

use App\Http\Resources\CommentResource;
use App\Models\Comment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class CommentCreated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public readonly Comment $comment) {}

    public function broadcastOn(): Channel {
        return new Channel('comments');
    }

    public function broadcastAs(): string {
        return 'comment.created';
    }

    public function broadcastWith(): array {
        $payload = (new CommentResource($this->comment))->resolve();
        $payload['parent_id'] = $this->comment->parent_id;

        return $payload;
    }
}
