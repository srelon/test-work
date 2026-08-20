<?php

use App\Jobs\PersistCommentDirectly;

return [
    'fallback_jobs' => [
        'comments_create' => PersistCommentDirectly::class,
    ],
];
