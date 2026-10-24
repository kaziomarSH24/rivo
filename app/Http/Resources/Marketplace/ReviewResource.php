<?php

namespace App\Http\Resources\Marketplace;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $userName = $this->is_anonymous ? 'Anonymous User' : ($this->user?->name ?? 'Customer');
        $userAvatar = $this->is_anonymous ? null : format_media_url($this->user?->avatar, 'avatars');

        $photos = [];
        if (!empty($this->photos) && is_array($this->photos)) {
            $photos = array_values(array_map(fn($p) => format_media_url($p, 'reviews'), $this->photos));
        }

        $type = $this->reviewable_type;
        if ($type === Store::class || $type === 'store') {
            $typeStr = 'store';
        } else {
            $typeStr = 'product';
        }

        return [
            'id' => $this->id,
            'reviewable_type' => $typeStr,
            'reviewable_id' => (int) $this->reviewable_id,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'photos' => $photos,
            'is_anonymous' => (bool) $this->is_anonymous,
            'is_verified_purchase' => !empty($this->order_id),
            'order_id' => $this->order_id ? (int) $this->order_id : null,
            'user' => [
                'id' => $this->is_anonymous ? null : $this->user?->id,
                'name' => $userName,
                'avatar' => $userAvatar,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->diffForHumans() ?? 'recently',
        ];
    }
}