<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorOrderCardResource extends JsonResource
{
    /**
     * Transform the order into a card representation matching Figma Vendor Orders Queue screens.
     */
    public function toArray(Request $request): array
    {
        // 1. Status Badge & Color
        $statusColors = [
            'new' => 'blue',
            'preparing' => 'amber',
            'ready_for_pickup' => 'purple',
            'out_for_delivery' => 'indigo',
            'delivered' => 'green',
            'cancelled' => 'red',
        ];

        $statusLabels = [
            'new' => 'New',
            'preparing' => 'Preparing',
            'ready_for_pickup' => 'Ready',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ];

        $statusColor = $statusColors[$this->status] ?? 'grey';
        $statusLabel = $statusLabels[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));

        // 2. Relative time calculation ("2 min ago", "10 min ago", etc.)
        $timeAgo = $this->created_at ? $this->created_at->diffForHumans(['short' => false]) : null;

        // 3. Customer Info & Initials
        $customerName = $this->user?->name ?? 'Customer';
        $words = explode(' ', trim($customerName));
        $initials = '';
        foreach ($words as $w) {
            $initials .= strtoupper(substr($w, 0, 1));
            if (strlen($initials) >= 2) break;
        }

        // 4. Delivery Address Snippet
        $isPickup = $this->delivery_type === 'pickup';
        if ($isPickup) {
            $addressSnippet = 'Store Pickup';
        } elseif ($this->address) {
            $parts = array_filter([$this->address->house_no, $this->address->street_address, $this->address->city, $this->address->state]);
            $addressSnippet = implode(', ', $parts);
        } elseif (!empty($this->delivery_address_json)) {
            $addressSnippet = $this->delivery_address_json['street_address'] ?? ($this->delivery_address_json['city'] ?? 'Delivery Address');
        } else {
            $addressSnippet = 'Delivery order';
        }

        // 5. Items count & Summary
        $itemsCount = $this->relationLoaded('items') ? $this->items->sum('quantity') : (int) ($this->items_count ?? 0);
        $itemsSummary = $itemsCount === 1 ? '1 item' : "{$itemsCount} items";

        // 6. Primary item image preview
        $primaryItemImage = null;
        if ($this->relationLoaded('items') && $this->items->isNotEmpty()) {
            $firstItem = $this->items->first();
            $rawImg = $firstItem->image ?: ($firstItem->product?->images[0] ?? null);
            $primaryItemImage = format_media_url($rawImg, 'products');
        }

        // 7. Action buttons availability matching Figma UI
        $availableActions = match ($this->status) {
            'new' => ['accept', 'reject'],
            'preparing' => ['mark_ready', 'view_fulfillment'],
            'ready_for_pickup' => ['out_for_delivery'],
            'out_for_delivery' => ['mark_delivered', 'mark_delayed'],
            default => [],
        };

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'status_label' => $statusLabel,
            'status_color' => $statusColor,
            'time_ago' => $timeAgo,
            'customer' => [
                'id' => $this->user?->id,
                'name' => $customerName,
                'initials' => $initials ?: 'CU',
                'avatar' => format_media_url($this->user?->avatar, 'avatars'),
                'phone' => $this->user?->phone_number,
                'email' => $this->user?->email,
            ],
            'delivery_type' => $this->delivery_type ?: 'delivery',
            'delivery_type_label' => $isPickup ? 'Store Pickup' : 'Delivery order',
            'address_snippet' => $addressSnippet,
            'items_count' => (int) $itemsCount,
            'items_summary' => $itemsSummary,
            'primary_item_image' => $primaryItemImage,
            'total' => (float) $this->total,
            'formatted_total' => '$' . number_format($this->total, 2),
            'available_actions' => $availableActions,
            'placed_at' => $this->placed_at?->toISOString() ?? $this->created_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
