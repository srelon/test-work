<?php

namespace App\Services;

use App\Events\CommentCreated;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Services\Resilience\ReliableQueue;
use App\Services\Resilience\ResilientCache;
use App\Traits\SavesBase64Images;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as ConcreteLengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Throwable;

class CommentService
{
    use SavesBase64Images;

    public function __construct(
        private ResilientCache $cache,
        private ReliableQueue $reliableQueue,
    ) {}

    private const ALLOWED_TAGS = '<a><code><i><strong>';

    private const ORIGINAL_MAX_WIDTH = 1920;

    private const ORIGINAL_MAX_HEIGHT = 1080;

    private const CROPPED_WIDTH = 320;

    private const CROPPED_HEIGHT = 240;

    private const CACHE_TTL = 3600;

    public function getPaginated(array $filters, int $perPage = 25): LengthAwarePaginator {
        $sort_by = $filters['sort_by'] ?? 'newest';
        $page = $filters['page'] ?? 1;

        $cached = $this->cache->remember(
            "comments:index:{$sort_by}:{$page}",
            self::CACHE_TTL,
            function () use ($sort_by, $perPage, $page) {
                $query = Comment::query()->whereNull('parent_id');

                $this->applySort($query, $sort_by);

                $paginated = $query->paginate($perPage, ['*'], 'page', $page)
                    ->through(fn (Comment $comment) => (new CommentResource($comment))->resolve());

                return [
                    'data' => $paginated->items(),
                    'total' => $paginated->total(),
                    'per_page' => $paginated->perPage(),
                    'current_page' => $paginated->currentPage(),
                ];
            },
            ['comments_list'],
        );

        $this->hydrateRepliesCount($cached['data']);

        return new ConcreteLengthAwarePaginator($cached['data'], $cached['total'], $cached['per_page'], $cached['current_page']);
    }

    public function getReplies(Comment $comment): array {
        return $this->cache->remember(
            "comments:replies:{$comment->id}",
            self::CACHE_TTL,
            fn () => $comment->replies()->oldest()->with('repliedTo')->get()
                ->map(fn (Comment $reply) => (new CommentResource($reply))->resolve())
                ->all(),
        );
    }

    public function enqueue(array $data): void {
        $data['text'] = $this->sanitizeBody($data['text']);

        $this->reliableQueue->send(config('rabbitmq.queues.comments_create'), $data);
    }

    public function persist(array $data): void {
        try {
            $imageColumns = $this->storeImage($data['image'] ?? null);
        } catch (Throwable $e) {
            Log::warning('Comment image processing failed, comment was not persisted.', [
                'user_name' => $data['user_name'] ?? null,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }

        $comment = Comment::create([
            'parent_id' => $data['parent_id'] ?? null,
            'replied_to_comment_id' => $data['replied_to_comment_id'] ?? null,
            'user_name' => $data['user_name'],
            'email' => $data['email'],
            'home_page' => $data['home_page'] ?? null,
            'body' => $data['text'],
            ...$imageColumns,
        ]);

        $comment->load('repliedTo');

        try {
            if ($comment->parent_id === null) {
                $this->cache->flushTags(['comments_list']);
                $this->cache->forever($this->repliesCountKey($comment->id), 0);
            } else {
                $count = Comment::where('parent_id', $comment->parent_id)->count();
                $this->cache->forever($this->repliesCountKey($comment->parent_id), $count);
                $this->cache->forget("comments:replies:{$comment->parent_id}");
            }
        } catch (Throwable $e) {
            report($e);
        }

        try {
            CommentCreated::dispatch($comment);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function hydrateRepliesCount(array &$items): void {
        if (empty($items)) {
            return;
        }

        $keys = collect($items)->mapWithKeys(fn (array $item) => [$item['id'] => $this->repliesCountKey($item['id'])]);

        $cachedCounts = $this->cache->many($keys->values()->all());

        $missingIds = $keys->filter(fn (string $cache_key) => $cachedCounts[$cache_key] === null)->keys();

        $freshCounts = $missingIds->isEmpty()
            ? collect()
            : Comment::whereIn('parent_id', $missingIds->all())
                ->selectRaw('parent_id, count(*) as aggregate')
                ->groupBy('parent_id')
                ->pluck('aggregate', 'parent_id');

        foreach ($items as &$item) {
            $count = (int) ($cachedCounts[$keys[$item['id']]] ?? $freshCounts[$item['id']] ?? 0);

            if ($missingIds->contains($item['id'])) {
                $this->cache->forever($keys[$item['id']], $count);
            }

            $item['replies_count'] = $count;
        }

        unset($item);
    }

    protected function repliesCountKey(int $commentId): string {
        return "comments:{$commentId}:replies_count";
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
