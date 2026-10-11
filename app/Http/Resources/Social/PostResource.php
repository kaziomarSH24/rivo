<?php

namespace App\Http\Resources\Social;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\Pet\PetResource;
use App\Models\WalkSession;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id;

        return [
            'id' => $this->id,
            'content' => $this->content,
            'type' => $this->type,
            'location' => $this->location,
            'visibility' => $this->visibility ?? 'public',
            'hide_like_count' => (bool) $this->hide_like_count,
            'hide_share_count' => (bool) $this->hide_share_count,
            'turn_off_commenting' => (bool) $this->turn_off_commenting,
            'likes_count' => (int) $this->likes_count,
            'comments_count' => (int) $this->comments_count,
            'shares_count' => (int) $this->shares_count,
            'is_liked' => $userId && $this->relationLoaded('likes')
                ? $this->likes->contains('user_id', $userId)
                : false,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'pet' => new PetResource($this->whenLoaded('pet')),
            'media' => PostMediaResource::collection($this->whenLoaded('media')),
            'comments' => PostCommentResource::collection($this->whenLoaded('comments')),
            'walk_summary' => $this->when(
                $this->type === 'walk_summary' && $this->postable_type === WalkSession::class,
                function () {
                    return $this->postable ? new PostWalkSummaryResource($this->postable) : null;
                }
            ),
            'postable' => $this->whenLoaded('postable', function () {
                if ($this->postable instanceof WalkSession) {
                    return new PostWalkSummaryResource($this->postable);
                }
                return $this->postable;
            }),
        ];
    }
}
