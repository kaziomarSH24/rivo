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

                if ($this->latestMessage->is_system) {
                    return Str::limit($this->latestMessage->body, 35);
                }

                $user = auth('sanctum')->user() ?? request()->user() ?? auth()->user();
                $currentUserId = $user?->id;

                // check if the last message was sent by another user
                if (!$currentUserId || $this->latestMessage->user_id !== $currentUserId) {
                    $senderFirstName = $this->latestMessage->user ? Str::before($this->latestMessage->user->name, ' ') : 'System';

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
                $currentUserId = auth('sanctum')->id() ?? request()->user()?->id ?? auth()->id();
                if ($this->users->count() === 2) {
                    $otherUser = $this->users->firstWhere('id', '!=', $currentUserId);
                    return $otherUser ? $otherUser->name : 'Conversation';
                }
                return 'Group Chat';
            }
        );
    }

    /**
     * Find or create a direct 1-on-1 conversation between two users
     */
    public static function findOrCreateDirect(int $userId1, int $userId2): self
    {
        $userIds = [$userId1, $userId2];
        sort($userIds);

        $conversation = self::whereNull('name')
            ->whereHas('users', fn($q) => $q->where('users.id', $userId1))
            ->whereHas('users', fn($q) => $q->where('users.id', $userId2))
            ->has('users', '=', 2)
            ->first();

        if (!$conversation) {
            $conversation = self::create([
                'created_by' => $userId1,
            ]);
            $conversation->users()->sync($userIds);
        }

        return $conversation;
    }
}
