<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'created_by'];
    protected $appends = ['last_message_preview', 'display_name'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    //relationship with conversation creator
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // check if a user is admin of the conversation
    public function isAdmin(User $user)
    {
        // Cache the pivot data to avoid multiple queries
        $pivot = $this->users()->where('user_id', $user->id)->first()?->pivot;
        return $pivot && $pivot->role === 'admin';
    }

    // last message preview attribute
    protected function lastMessagePreview(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->latestMessage) {
                    return null;
                }

                $user = auth()->user();

                // check if the last message was sent by another user
                if ($this->latestMessage->user_id !== $user->id) {
                    // fetch first name of the sender for preview (like Omar faruk -> Omar)
                    $senderFirstName = Str::before($this->latestMessage->user->name, ' ');

                    if ($this->latestMessage->media_type) {
                        $mediaText = match ($this->latestMessage->media_type) {
                            'image' => 'sent a photo',
                            'video' => 'sent a video',
                            'audio' => 'sent an audio',
                            default => 'sent a file',
                        };
                        return "{$senderFirstName} {$mediaText}";
                    }

                    return Str::limit($this->latestMessage->body, 30);
                }

                // last message was sent by the authenticated user
                $prefix = 'You: ';
                if ($this->latestMessage->media_type) {
                    $prefix = 'You ';
                    $mediaText = match ($this->latestMessage->media_type) {
                        'image' => 'sent a photo',
                        'video' => 'sent a video',
                        'audio' => 'sent an audio',
                        default => 'sent a file',
                    };
                    return $prefix . $mediaText;
                }

                return $prefix . Str::limit($this->latestMessage->body, 30);
            },
        );
    }

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: function () {

                if ($this->name) {
                    return $this->name;
                }
                if ($this->users->count() === 2) {
                    $otherUser = $this->users->firstWhere('id', '!=', auth()->id());
                    return $otherUser ? $otherUser->name : 'Conversation';
                }
                return 'Group Chat';
            }
        );
    }
}
