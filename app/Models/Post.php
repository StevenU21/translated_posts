<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = [
        'user_id',
        'default_locale'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function postTranslations()
    {
        return $this->hasMany(PostTranslation::class);
    }
}
