<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailsResource extends JsonResource
{
    /**
     * Transform the resource into an array matching Figma Order Completed & Order Details screens.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'delivery_type' => $this->delivery_type,
            'estimated_delivery_time' => $this->estimated_delivery_time,
            'delivery_address' => $this->delivery_address_json,
            'store' => $this->whenLoaded('store', function () {
                return [
                    'id' => $this->store->id,
                    'name' => $this->store->name,
                    'slug' => $this->store->slug,
                    'logo' => $this->store->logo,
                    'phone' => $this->store->phone,
                    'street_address' => $this->store->street_address,
                ];
            }),
            'items_count' => $this->items ? $this->items->count() : 0,
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
                        'product_name' => $item->product_name,
                        'variant_flavor' => $item->variant_flavor,
                        'variant_size' => $item->variant_size,
                        'price' => (float) $item->price,
                        'quantity' => (int) $item->quantity,
                        'total' => (float) $item->total,
                        'image' => $item->image,
                    ];
                });
            }),
            'summary' => [
                'subtotal' => (float) $this->subtotal,
                'delivery_fee' => (float) $this->delivery_fee,
                'tax' => (float) $this->tax,
                'discount' => (float) $this->discount,
                'total' => (float) $this->total,
            ],
            'payment' => [
                'method' => $this->payment_method,
                'status' => $this->payment_status,
                'transaction_id' => $this->transaction_id,
                'transaction' => $this->whenLoaded('transaction', function () {
                    return $this->transaction ? [
                        'id' => $this->transaction->id,
                        'gateway' => $this->transaction->gateway,
                        'status' => $this->transaction->status,
                        'amount' => (float) $this->transaction->amount,
                        'currency' => $this->transaction->currency,
                        'payment_intent_id' => $this->transaction->payment_intent_id,
                    ] : null;
                }),
            ],
            'notes' => $this->notes,
            'placed_at' => $this->placed_at?->toISOString() ?: $this->created_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
        ];
    }
}
