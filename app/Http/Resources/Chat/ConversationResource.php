<?php

namespace App\Http\Resources\Chat;

use App\Http\Resources\UserResource;
use Carbon\Carbon;
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
        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'unread_count' => $this->when(isset($this->unread_count), $this->unread_count),
            'last_message_preview' => $this->last_message_preview,
            'last_message_time' => $this->whenLoaded('latestMessage', function () {
                return $this->formatTimestamp($this->latestMessage->created_at);
            }),
            'users' => UserResource::collection($this->whenLoaded('users')),
            'latest_message' => new MessageResource($this->whenLoaded('latestMessage')),
        ];
    }

}
