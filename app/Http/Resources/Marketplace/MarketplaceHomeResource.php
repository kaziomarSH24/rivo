<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceHomeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'delivery_address' => $this->resource['delivery_address'] ?? null,
            'pro_banner' => $this->resource['pro_banner'] ?? [
                'title' => 'Rivo Pro',
                'subtitle' => 'Unlimited $0 Delivery Fee',
                'badge' => 'Active Member Benefit',
            ],
            'categories' => CategoryResource::collection($this->resource['categories'] ?? []),
            'popular_stores' => StoreCardResource::collection($this->resource['popular_stores'] ?? []),
            'recommended_for_pet' => [
                'pet_name' => $this->resource['recommended_for_pet']['pet_name'] ?? 'Your Pet',
                'pet_type' => $this->resource['recommended_for_pet']['pet_type'] ?? 'dog',
                'products' => ProductCardResource::collection($this->resource['recommended_for_pet']['products'] ?? []),
            ],
            'top_deals' => ProductCardResource::collection($this->resource['top_deals'] ?? []),
        ];
    }
}
