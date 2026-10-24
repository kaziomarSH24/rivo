<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorProductCardResource extends JsonResource
{
    /**
     * Transform the product model into an array matching Figma "Products" catalog screen.
     */
    public function toArray(Request $request): array
    {
        // 1. Primary image URL
        $primaryImage = null;
        if (!empty($this->images)) {
            $primaryImage = is_array($this->images) ? ($this->images[0] ?? null) : $this->images;
        }

        // 2. Status Badge determination
        $status = 'active';
        $statusLabel = 'Active';
        $statusColor = 'green';

        if (!$this->is_active) {
            $status = 'draft';
            $statusLabel = 'Draft';
            $statusColor = 'grey';
        } elseif ($this->stock <= 0) {
            $status = 'out_of_stock';
            $statusLabel = 'Out of Stock';
            $statusColor = 'red';
        }

        // 3. Stock label & warning
        $stockLabel = "{$this->stock} in stock";
        $stockStatus = 'in_stock';

        if ($this->stock <= 0) {
            $stockLabel = 'Out of stock';
            $stockStatus = 'out_of_stock';
        } elseif ($this->stock <= ($this->low_stock_threshold ?? 5)) {
            $stockLabel = "{$this->stock} in stock";
            $stockStatus = 'low_stock';
        }

        // 4. Subtitle: Brand · Category
        $brandName = $this->brand?->name;
        $categoryName = $this->category?->name;
        $subtitleParts = array_filter([$brandName, $categoryName]);
        $subtitle = !empty($subtitleParts) ? implode(' · ', $subtitleParts) : 'General';

        // 5. Pricing & Strikethrough
        $regularPrice = (float) $this->price;
        $discountPrice = $this->discount_price ? (float) $this->discount_price : null;
        $displayPrice = $discountPrice ?? $regularPrice;
        $strikethroughPrice = $discountPrice ? $regularPrice : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'subtitle' => $subtitle,
            'image' => format_media_url($primaryImage, 'products'),
            'images' => array_map(fn($img) => format_media_url($img, 'products'), (array) ($this->images ?? [])),
            'price' => $displayPrice,
            'formatted_price' => '$' . number_format($displayPrice, 2),
            'original_price' => $strikethroughPrice,
            'formatted_original_price' => $strikethroughPrice ? '$' . number_format($strikethroughPrice, 2) : null,
            'stock' => (int) $this->stock,
            'stock_status' => $stockStatus,
            'stock_label' => $stockLabel,
            'status' => $status,
            'status_label' => $statusLabel,
            'status_color' => $statusColor,
            'is_active' => (bool) $this->is_active,
            'is_online' => (bool) $this->is_online,
            'is_instore' => (bool) $this->is_instore,
            'rating' => (float) ($this->rating ?? 5.0),
            'reviews_count' => (int) ($this->reviews_count ?? 0),
            'sales_count' => (int) ($this->sales_count ?? 0),
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null,
            'brand' => $this->brand ? [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
