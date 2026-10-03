<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'pet_id' => $this->pet_id,
            'content' => $this->content,
            'type' => $this->type,
            'postable_id' => $this->postable_id,
            'postable_type' => $this->postable_type,
            'likes_count' => $this->likes_count,
            'comments_count' => $this->comments_count,
            'shares_count' => $this->shares_count,
            'created_at' => $this->created_at,
            'postable' => $this->whenLoaded('postable', function () {
                // If it's a LostPetReport, return its specific resource
                if (class_basename($this->postable_type) === 'LostPetReport') {
                    return new LostPetReportResource($this->postable);
                }
                return $this->postable;
            }),
        ];
    }
}

