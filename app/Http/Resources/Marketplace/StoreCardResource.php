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
            'logo' => format_media_url($this->logo, 'stores/logos'),
            'banner' => format_media_url($this->banner, 'stores/banners'),
            'rating' => (float) $this->rating,
            'reviews_count' => (int) $this->reviews_count,
            'delivery_fee' => (float) $this->delivery_fee,
            'delivery_time_text' => $this->delivery_time_text ?: '~15 min',
            'street_address' => $this->street_address,
            'city' => $this->city,
            'is_verified' => (bool) $this->is_verified,
            'is_live' => (bool) $this->is_live,

            // Dynamic relations & conditional attributes (when requested via ?include=...)
            'products' => ProductCardResource::collection($this->whenLoaded('products')),
            'products_count' => $this->when(!is_null($this->products_count), fn() => (int) $this->products_count),
            'products_exists' => $this->when(!is_null($this->products_exists), fn() => (bool) $this->products_exists),

            'documents' => $this->whenLoaded('documents'),
            'documents_count' => $this->when(!is_null($this->documents_count), fn() => (int) $this->documents_count),
            'documents_exists' => $this->when(!is_null($this->documents_exists), fn() => (bool) $this->documents_exists),

            'reviews' => $this->whenLoaded('reviews'),
            'reviews_exists' => $this->when(!is_null($this->reviews_exists), fn() => (bool) $this->reviews_exists),
        ];
    }
}
