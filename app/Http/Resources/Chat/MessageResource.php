<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    use FormatsTimestamps;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUserId = auth('sanctum')->id() ?? $request->user()?->id ?? auth()->id();
        $isSender = $this->user_id && $this->user_id === $currentUserId;

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'body' => $this->body,
            'is_system' => (bool) $this->is_system,
            'media_url' => $this->media_url,
            'media_type' => $this->media_type,
            'parent_id' => $this->parent_id,
            'sent_at' => $this->created_at,
            'sent_at_formatted' => $this->formatTimestamp($this->created_at),
            'sender' => $this->user_id ? [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'avatar' => $this->user?->avatar ? (str_starts_with($this->user->avatar, 'http') ? $this->user->avatar : asset('storage/' . $this->user->avatar)) : null,
            ] : null,
            'is_sender' => $isSender,
            'is_read' => (bool) $this->is_read,
        ];
    }
}
