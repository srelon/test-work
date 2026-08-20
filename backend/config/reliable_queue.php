<?php

return [
    'fallback_jobs' => [
        'comments_create' => \App\Jobs\PersistCommentDirectly::class,
    ],
];
