<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderCardResource extends JsonResource
{
    /**
     * Transform the resource into an array matching Figma My Orders cards.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'delivery_type' => $this->delivery_type,
            'estimated_delivery_time' => $this->estimated_delivery_time,
            'total' => (float) $this->total,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'store' => [
                'id' => $this->store?->id,
                'name' => $this->store?->name,
                'slug' => $this->store?->slug,
                'logo' => $this->store?->logo,
            ],
            'items_count' => $this->items ? $this->items->count() : 0,
            'first_item' => $this->items && $this->items->isNotEmpty() ? [
                'product_name' => $this->items->first()->product_name,
                'image' => $this->items->first()->image,
                'quantity' => $this->items->first()->quantity,
            ] : null,
            'placed_at' => $this->placed_at?->toISOString() ?: $this->created_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
        ];
    }
}
