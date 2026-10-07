<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function media() { return $this->hasMany(PostMedia::class)->orderBy('order'); }
    public function comments() { return $this->hasMany(PostComment::class); }
    public function likes() { return $this->hasMany(PostLike::class); }
    public function postable()
    {
        return $this->morphTo();
    }
}

