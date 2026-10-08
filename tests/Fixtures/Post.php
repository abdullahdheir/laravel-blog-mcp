<?php

namespace AbdullahDheir\BlogMcp\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Post extends Model
{
    protected $table = 'posts';

    protected $casts = ['published_at' => 'datetime', 'notify_subscribers' => 'boolean'];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_post');
    }
}
