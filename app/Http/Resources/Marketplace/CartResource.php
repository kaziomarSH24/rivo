<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * Transform the cart resource into an array matching Figma Cart Screen.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $store = $this->resource['store'] ?? null;
        $items = $this->resource['items'] ?? [];
        $summary = $this->resource['summary'] ?? [];

        $formattedItems = [];
        foreach ($items as $item) {
            $product = $item->product;
            $variant = $item->variant;

            $unitPrice = $variant && $variant->price
                ? (float) $variant->price
                : ($product && $product->discount_price ? (float) $product->discount_price : (float) ($product?->price ?? 0));
            $itemTotal = round($unitPrice * $item->quantity, 2);

            $rawImages = $product && is_array($product->images) ? $product->images : [];
            $images = array_values(array_filter(array_map(fn($img) => format_media_url($img, 'products'), $rawImages)));
            $primaryImage = !empty($images) ? $images[0] : null;

            $variantText = [];
            if ($variant) {
                if ($variant->flavor) $variantText[] = $variant->flavor;
                if ($variant->size_lbs) $variantText[] = $variant->size_lbs;
            }

            $maxStock = $variant ? (int) $variant->stock : ($product ? (int) $product->stock : 0);

            $formattedItems[] = [
                'id' => $item->id,
                'cart_id' => $item->cart_id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'name' => $product?->name ?? 'Unknown Product',
                'primary_image' => $primaryImage,
                'variant_details' => !empty($variantText) ? implode(', ', $variantText) : null,
                'unit_price' => $unitPrice,
                'quantity' => (int) $item->quantity,
                'item_total' => $itemTotal,
                'max_stock' => $maxStock,
                'is_in_stock' => $maxStock >= $item->quantity,
            ];
        }

        return [
            'cart_id' => $this->resource['cart_id'] ?? null,
            'store' => $store ? [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug ?? null,
                'logo' => format_media_url($store->logo, 'stores/logos'),
                'delivery_fee' => (float) $store->delivery_fee,
                'delivery_time_text' => $store->delivery_time_text ?: '~15 min',
            ] : null,
            'items_count' => (int) ($this->resource['items_count'] ?? 0),
            'items' => $formattedItems,
            'summary' => $summary,
        ];
    }
}
