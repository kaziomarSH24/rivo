<?php

namespace App\Http\Resources\Chat;

use App\Http\Resources\UserResource;
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
        $isSender = $this->user_id === $request->user()?->id;
        return [
            'id' => $this->id,
            'body' => $this->body,
            'media_url' => $this->media_url,
            'media_type' => $this->media_type,
            'sent_at' => $this->created_at,
            'sent_at_formatted' => $this->formatTimestamp($this->created_at),
            'sender' => new UserResource($this->whenLoaded('user')),
            'is_sender' => $isSender,
            'is_read' => (bool) $this->is_read,
        ];
    }
}
