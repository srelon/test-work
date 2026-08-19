<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommentFilterRequest;
use App\Http\Requests\CommentRequest;
use App\Http\Resources\CommentResource;
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

        return $this->respondWithJson((new CommentResource($comment))->resolve(), 201);
    }
}
