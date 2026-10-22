<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchResultResource extends JsonResource
{
    /**
     * Transform the search result into an array matching Figma Search screen.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $products = $this->resource['products'] ?? [];
        $stores = $this->resource['stores'] ?? [];

        $formattedProducts = [];
        if (is_object($products) && method_exists($products, 'items')) {
            $formattedProducts = ProductCardResource::collection($products)->response()->getData(true);
        } elseif (is_array($products)) {
            $formattedProducts = $products;
        }

        $formattedStores = [];
        if (is_object($stores) && method_exists($stores, 'items')) {
            $formattedStores = StoreCardResource::collection($stores)->response()->getData(true);
        } elseif (is_array($stores)) {
            $formattedStores = $stores;
        }

        return [
            'recent_searches' => $this->resource['recent_searches'] ?? [],
            'popular_tags' => $this->resource['popular_tags'] ?? [],
            'products' => $formattedProducts,
            'stores' => $formattedStores,
        ];
    }
}
