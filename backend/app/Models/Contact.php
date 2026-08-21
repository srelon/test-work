<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $fillable = [
        'user_name',
        'email',
    ];

    public function comments(): HasMany {
        return $this->hasMany(Comment::class);
    }
}
