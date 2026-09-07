<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['conversation_id', 'user_id', 'body', 'media_url', 'media_type', 'parent_id', 'edited_at'];
    protected $with = ['user'];

    protected $touches = ['conversation'];

    /**
     * get the parent message (for threading support)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'parent_id');
    }

    /**
     * get all replies of a message (threading support)
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'parent_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function readBy()
    {
        return $this->belongsToMany(User::class, 'message_read_user', 'message_id', 'user_id');
    }

    //media url accessor to return full url
    public function getMediaUrlAttribute($value)
    {
        return $value ? asset('storage/' . $value) : null;
    }
}
