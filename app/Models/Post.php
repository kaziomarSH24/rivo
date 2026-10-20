<?php

namespace App\Models;

use App\Traits\AutoClearsCache;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use AutoClearsCache;
    protected $guarded = ['id'];

    protected $casts = [
        'metadata' => 'array',
        'reaction_counts' => 'array',
        'hide_like_count' => 'boolean',
        'hide_share_count' => 'boolean',
        'turn_off_commenting' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function media()
    {
        return $this->hasMany(PostMedia::class)->orderBy('order');
    }

    public function comments()
    {
        return $this->hasMany(PostComment::class);
    }

    public function reactions()
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    public function likes()
    {
        return $this->reactions();
    }

        public function postable()
    {
        return $this->morphTo();
    }

    public function bookmarks()
    {
        return $this->hasMany(PostBookmark::class);
    }

    public function isBookmarkedBy(?User $user): bool
    {
        if (!$user) return false;
        return $this->bookmarks()->where('user_id', $user->id)->exists();
    }
}

