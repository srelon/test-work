<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommentFilterRequest;
use App\Http\Requests\CommentRequest;
use App\Models\Comment;
use App\Services\CommentService;

class CommentController extends Controller
{
    public function __construct(protected CommentService $commentService) {}

    public function index(CommentFilterRequest $request) {
        $paginated = $this->commentService->getPaginated($request->filters());

        return $this->respondWithJson(['items' => $paginated]);
    }

    public function store(CommentRequest $request) {
        $comment = $this->commentService->create($request->validated());

        return $this->respondWithJson($comment, 201);
    }

    public function replies(Comment $comment) {
        abort_if($comment->parent_id !== null, 404);

        return $this->respondWithJson(['replies' => $this->commentService->getReplies($comment)]);
    }
}
