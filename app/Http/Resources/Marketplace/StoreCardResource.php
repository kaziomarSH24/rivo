<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'logo' => $this->logo,
            'banner' => $this->banner,
            'rating' => (float) $this->rating,
            'reviews_count' => (int) $this->reviews_count,
            'delivery_fee' => (float) $this->delivery_fee,
            'delivery_time_text' => $this->delivery_time_text ?: '~15 min',
            'street_address' => $this->street_address,
            'city' => $this->city,
            'is_verified' => (bool) $this->is_verified,
            'is_live' => (bool) $this->is_live,
        ];
    }
}
