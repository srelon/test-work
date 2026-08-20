<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    protected $fillable = [
        'parent_id',
        'replied_to_comment_id',
        'user_name',
        'email',
        'home_page',
        'body',
        'images',
    ];

    protected $casts = [
        'images' => 'array',
    ];

    public function parent(): BelongsTo {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function repliedTo(): BelongsTo {
        return $this->belongsTo(Comment::class, 'replied_to_comment_id');
    }

    public function replies(): HasMany {
        return $this->hasMany(Comment::class, 'parent_id');
    }
}
