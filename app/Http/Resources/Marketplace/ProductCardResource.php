<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hasDiscount = !empty($this->discount_price) && $this->discount_price > 0 && $this->discount_price < $this->price;
        $discountPercentage = $hasDiscount && $this->price > 0
            ? (int) round((($this->price - $this->discount_price) / $this->price) * 100)
            : 0;

        $images = is_array($this->images) ? $this->images : [];

        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'pet_type' => $this->pet_type,
            'price' => (float) $this->price,
            'discount_price' => $this->discount_price ? (float) $this->discount_price : null,
            'has_discount' => $hasDiscount,
            'discount_percentage' => $discountPercentage,
            'stock' => (int) $this->stock,
            'is_in_stock' => (int) $this->stock > 0,
            'primary_image' => !empty($images) ? $images[0] : null,
            'images' => $images,
            'rating' => (float) $this->rating,
            'reviews_count' => (int) $this->reviews_count,
            'sales_count' => (int) $this->sales_count,
            'brand' => $this->whenLoaded('brand', function () {
                return $this->brand ? [
                    'id' => $this->brand->id,
                    'name' => $this->brand->name,
                    'slug' => $this->brand->slug,
                    'logo' => $this->brand->logo,
                ] : null;
            }),
            'store' => $this->whenLoaded('store', function () {
                return $this->store ? [
                    'id' => $this->store->id,
                    'name' => $this->store->name,
                    'slug' => $this->store->slug,
                    'delivery_time_text' => $this->store->delivery_time_text ?: '~15 min',
                    'delivery_fee' => (float) $this->store->delivery_fee,
                    'logo' => $this->store->logo,
                ] : null;
            }),
        ];
    }
}
