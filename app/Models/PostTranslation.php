<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LaravelLang\Models\Eloquent\Translation;

class PostTranslation extends Model
{
    protected $fillable = [
        'post_id',
        'locale',
        'title',
        'content',
        'status',
        'slug'
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function translation(): BelongsTo
    {
        return $this->belongsTo(Translation::class, 'locale', 'locale');
    }
}
