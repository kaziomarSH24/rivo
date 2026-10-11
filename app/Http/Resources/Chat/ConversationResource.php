<?php

namespace App\Http\Resources\Chat;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    use FormatsTimestamps;

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $currentUserId = auth('sanctum')->id() ?? $request->user()?->id ?? auth()->id();

        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'unread_count' => $this->when(isset($this->unread_count), $this->unread_count),
            'last_message_preview' => $this->last_message_preview,
            'last_message_time' => $this->whenLoaded('latestMessage', function () {
                return $this->formatTimestamp($this->latestMessage->created_at);
            }),
            'other_user' => $this->whenLoaded('users', function () use ($currentUserId) {
                $other = $this->users->firstWhere('id', '!=', $currentUserId);
                return $other ? [
                    'id' => $other->id,
                    'name' => $other->name,
                    'avatar' => $other->avatar ? (str_starts_with($other->avatar, 'http') ? $other->avatar : asset('storage/' . $other->avatar)) : null,
                ] : null;
            }),
            'users' => $this->whenLoaded('users', function () {
                return $this->users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'avatar' => $user->avatar ? (str_starts_with($user->avatar, 'http') ? $user->avatar : asset('storage/' . $user->avatar)) : null,
                    ];
                });
            }),
            'latest_message' => new MessageResource($this->whenLoaded('latestMessage')),
        ];
    }
}
