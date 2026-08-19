<?php

namespace App\Services;

use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Traits\SavesBase64Images;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CommentService
{
    use SavesBase64Images;

    private const ALLOWED_TAGS = '<a><code><i><strong>';

    private const ORIGINAL_MAX_WIDTH = 1920;

    private const ORIGINAL_MAX_HEIGHT = 1080;

    private const CROPPED_WIDTH = 320;

    private const CROPPED_HEIGHT = 240;

    public function getPaginated(array $filters, int $perPage = 25): LengthAwarePaginator {
        $query = Comment::query()
            ->whereNull('parent_id')
            ->with(['replies' => fn ($query) => $query->oldest()->with('repliedTo')]);

        $this->applySort($query, $filters['sort_by'] ?? 'newest');

        return $query->paginate($perPage)->through(fn (Comment $comment) => (new CommentResource($comment))->resolve());
    }

    public function create(array $data): Comment {
        $comment = Comment::create([
            'parent_id' => $data['parent_id'] ?? null,
            'replied_to_comment_id' => $data['replied_to_comment_id'] ?? null,
            'user_name' => $data['user_name'],
            'email' => $data['email'],
            'home_page' => $data['home_page'] ?? null,
            'body' => $this->sanitizeBody($data['text']),
            ...$this->storeImage($data['image'] ?? null),
        ]);

        $comment->load('repliedTo');

        return $comment;
    }

    public function sanitizeBody(string $body): string {
        $body = preg_replace('/<\/p>\s*<p(?=[\s>\/])[^>]*>/i', "\n", $body);
        $body = preg_replace('/<\/?p(?=[\s>\/])[^>]*>/i', '', $body);
        $body = preg_replace('/<br\s*\/?>/i', "\n", $body);

        $stripped = strip_tags($body, self::ALLOWED_TAGS);
        $stripped = preg_replace('/<(code|i|strong)\b[^>]*>/i', '<$1>', $stripped);
        $stripped = preg_replace_callback('/<a\b[^>]*>/i', fn ($matches) => $this->sanitizeAnchorTag($matches[0]), $stripped);

        return trim($stripped);
    }

    protected function sanitizeAnchorTag(string $tag): string {
        preg_match('/href\s*=\s*("[^"]*"|\'[^\']*\')/i', $tag, $href);
        preg_match('/title\s*=\s*("[^"]*"|\'[^\']*\')/i', $tag, $title);

        $url = $href ? trim($href[1], '"\'') : null;
        if ($url && ! preg_match('/^(https?:|mailto:)/i', $url)) {
            $url = null;
        }

        $attrs = $url ? ' href="'.htmlspecialchars($url, ENT_QUOTES).'"' : '';
        $attrs .= $title ? ' title="'.htmlspecialchars(trim($title[1], '"\''), ENT_QUOTES).'"' : '';
        $attrs .= ' rel="nofollow noindex"';

        return '<a'.$attrs.'>';
    }

    protected function applySort(Builder $query, string $sortBy): void {
        match ($sortBy) {
            'oldest' => $query->orderBy('created_at'),
            'user_name_asc' => $query->orderBy('user_name'),
            'user_name_desc' => $query->orderByDesc('user_name'),
            'email_asc' => $query->orderBy('email'),
            'email_desc' => $query->orderByDesc('email'),
            default => $query->orderByDesc('created_at'),
        };
    }

    protected function storeImage(?array $image): array {
        if (! $image) {
            return ['image_original' => null, 'image_cropped' => null];
        }

        return [
            'image_original' => $this->saveBase64ImageFit($image['original'], 'comments', self::ORIGINAL_MAX_WIDTH, self::ORIGINAL_MAX_HEIGHT),
            'image_cropped' => $this->saveBase64ImageCover($image['cropped'], 'comments', self::CROPPED_WIDTH, self::CROPPED_HEIGHT),
        ];
    }
}
