<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CommentResource extends JsonResource
{
    public function toArray($request): array {
        return [
            'id' => $this->id,
            'user_name' => $this->user_name,
            'email' => $this->email,
            'home_page' => $this->home_page,
            'text' => $this->body,
            'image' => $this->image_original ? [
                'original' => Storage::disk('public')->url($this->image_original),
                'cropped' => Storage::disk('public')->url($this->image_cropped),
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'replied_to' => $this->resource->repliedTo ? [
                'id' => $this->resource->repliedTo->id,
                'user_name' => $this->resource->repliedTo->user_name,
            ] : null,
            'replies_count' => $this->resource->parent_id === null ? ($this->resource->replies_count ?? 0) : 0,
        ];
    }
}
