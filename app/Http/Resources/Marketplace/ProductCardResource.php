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

        $rawImages = is_array($this->images) ? $this->images : [];
        $images = array_values(array_filter(array_map(fn($img) => format_media_url($img, 'products'), $rawImages)));
        $primaryImage = !empty($images) ? $images[0] : null;

        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
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
            'primary_image' => $primaryImage,
            'images' => $images,
            'rating' => (float) $this->rating,
            'reviews_count' => (int) $this->reviews_count,
            'sales_count' => (int) $this->sales_count,

            // Dynamic Relations (when requested via ?include=...)
            'brand' => $this->whenLoaded('brand', function () {
                return $this->brand ? [
                    'id' => $this->brand->id,
                    'name' => $this->brand->name,
                    'slug' => $this->brand->slug,
                    'logo' => format_media_url($this->brand->logo, 'brands/logos'),
                ] : null;
            }),
            'store' => $this->whenLoaded('store', function () {
                return $this->store ? [
                    'id' => $this->store->id,
                    'name' => $this->store->name,
                    'slug' => $this->store->slug,
                    'rating' => (float) $this->store->rating,
                    'reviews_count' => (int) $this->store->reviews_count,
                    'delivery_time_text' => $this->store->delivery_time_text ?: '~15 min',
                    'delivery_fee' => (float) $this->store->delivery_fee,
                    'logo' => format_media_url($this->store->logo, 'stores/logos'),
                ] : null;
            }),
            'category' => $this->whenLoaded('category', function () {
                return $this->category ? [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                    'icon' => format_media_url($this->category->icon, 'categories'),
                ] : null;
            }),
            'variants' => $this->whenLoaded('variants', function () {
                return $this->variants->map(function ($variant) {
                    return [
                        'id' => $variant->id,
                        'flavor' => $variant->flavor,
                        'size_lbs' => $variant->size_lbs,
                        'price' => (float) $variant->price,
                        'stock' => (int) $variant->stock,
                        'sku' => $variant->sku,
                    ];
                });
            }),
            'reviews' => $this->whenLoaded('reviews', function () {
                return $this->reviews->map(function ($rev) {
                    return [
                        'id' => $rev->id,
                        'rating' => (int) $rev->rating,
                        'comment' => $rev->comment,
                        'photos' => $rev->photos,
                        'created_at' => $rev->created_at?->toISOString(),
                    ];
                });
            }),

            // Dynamic Counts (when requested via Spatie *Count, e.g. ?include=storeCount,variantsCount)
            'store_count' => $this->when(!is_null($this->store_count), fn() => (int) $this->store_count),
            'category_count' => $this->when(!is_null($this->category_count), fn() => (int) $this->category_count),
            'brand_count' => $this->when(!is_null($this->brand_count), fn() => (int) $this->brand_count),
            'variants_count' => $this->when(!is_null($this->variants_count), fn() => (int) $this->variants_count),

            // Dynamic Exists (when requested via Spatie *Exists, e.g. ?include=variantsExists,reviewsExists)
            'store_exists' => $this->when(!is_null($this->store_exists), fn() => (bool) $this->store_exists),
            'category_exists' => $this->when(!is_null($this->category_exists), fn() => (bool) $this->category_exists),
            'brand_exists' => $this->when(!is_null($this->brand_exists), fn() => (bool) $this->brand_exists),
            'variants_exists' => $this->when(!is_null($this->variants_exists), fn() => (bool) $this->variants_exists),
            'reviews_exists' => $this->when(!is_null($this->reviews_exists), fn() => (bool) $this->reviews_exists),
        ];
    }
}
