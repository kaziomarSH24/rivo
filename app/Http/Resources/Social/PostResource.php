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

        $counts = $this->reaction_counts ?? [];
        if (empty($counts) && $this->relationLoaded('reactions')) {
            $raw = $this->reactions->groupBy('reaction_type')->map->count()->toArray();
            $counts = [
                'bone' => (int) ($raw['bone'] ?? 0),
                'fire' => (int) ($raw['fire'] ?? 0),
                'paw' => (int) ($raw['paw'] ?? 0),
                'heart' => (int) ($raw['heart'] ?? 0),
                'clap' => (int) ($raw['clap'] ?? 0),
            ];
        } else {
            $counts = [
                'bone' => (int) ($counts['bone'] ?? 0),
                'fire' => (int) ($counts['fire'] ?? 0),
                'paw' => (int) ($counts['paw'] ?? 0),
                'heart' => (int) ($counts['heart'] ?? 0),
                'clap' => (int) ($counts['clap'] ?? 0),
            ];
        }

        $userReaction = null;
        if ($userId && $this->relationLoaded('reactions')) {
            $userReaction = $this->reactions->firstWhere('user_id', $userId)?->reaction_type;
        }

        $totalLikes = (int) ($this->likes_count ?: array_sum($counts));

        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'type' => $this->type,
            'location' => $this->location,
            'visibility' => $this->visibility ?? 'public',
            'hide_like_count' => (bool) $this->hide_like_count,
            'hide_share_count' => (bool) $this->hide_share_count,
            'turn_off_commenting' => (bool) $this->turn_off_commenting,
            'likes_count' => $totalLikes,
            'is_liked' => $userReaction !== null,
            'user_reaction' => $userReaction,
            'reactions' => $counts,
            'comments_count' => (int) $this->comments_count,
            'shares_count' => (int) $this->shares_count,
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
            'achievement_summary' => $this->when($this->type === 'achievement', function () {
                return $this->metadata;
            }),
            'milestone_summary' => $this->when($this->type === 'milestone', function () {
                return $this->metadata;
            }),
            'metadata' => $this->metadata,
            'postable' => $this->whenLoaded('postable', function () {
                if ($this->postable instanceof WalkSession) {
                    return new PostWalkSummaryResource($this->postable);
                }
                return $this->postable;
            }),
        ];
    }
}
