<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorOrderDetailsResource extends JsonResource
{
    /**
     * Transform the order into full details matching Figma "Order Details" and "View Fulfillment" screens.
     */
    public function toArray(Request $request): array
    {
        // 1. Status metadata
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

        // 2. 4-Step Stepper (Screens 2 & 4: New -> Prep -> Ready -> Done)
        $stepSequence = [
            'new' => 1,
            'preparing' => 2,
            'ready_for_pickup' => 3,
            'out_for_delivery' => 3,
            'delivered' => 4,
            'cancelled' => 0,
        ];
        $currentStepNumber = $stepSequence[$this->status] ?? 1;

        $stepper = [
            'current_step' => $currentStepNumber,
            'steps' => [
                [
                    'key' => 'new',
                    'label' => 'New',
                    'step' => 1,
                    'is_completed' => $currentStepNumber >= 1 && $this->status !== 'cancelled',
                    'is_current' => $currentStepNumber === 1,
                ],
                [
                    'key' => 'prep',
                    'label' => 'Prep',
                    'step' => 2,
                    'is_completed' => $currentStepNumber >= 2 && $this->status !== 'cancelled',
                    'is_current' => $currentStepNumber === 2,
                ],
                [
                    'key' => 'ready',
                    'label' => 'Ready',
                    'step' => 3,
                    'is_completed' => $currentStepNumber >= 3 && $this->status !== 'cancelled',
                    'is_current' => $currentStepNumber === 3,
                ],
                [
                    'key' => 'done',
                    'label' => 'Done',
                    'step' => 4,
                    'is_completed' => $currentStepNumber === 4 && $this->status !== 'cancelled',
                    'is_current' => $currentStepNumber === 4,
                ],
            ],
        ];

        // 3. Fulfillment Timeline (Screen 5: View Fulfillment)
        $fulfillmentBadge = match ($this->status) {
            'new' => 'Order Received',
            'preparing' => 'Preparing',
            'ready_for_pickup' => 'Ready for Pickup',
            'out_for_delivery' => 'Courier Assigned',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            default => 'Pending',
        };

        $timelineSteps = [
            [
                'key' => 'preparing',
                'title' => 'Preparing',
                'description' => 'Merchant is preparing the items',
                'is_completed' => in_array($this->status, ['preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered']),
                'is_current' => $this->status === 'preparing',
            ],
            [
                'key' => 'ready_for_pickup',
                'title' => 'Ready for Pickup',
                'description' => 'Packed and waiting at store counter',
                'is_completed' => in_array($this->status, ['ready_for_pickup', 'out_for_delivery', 'delivered']),
                'is_current' => $this->status === 'ready_for_pickup',
            ],
            [
                'key' => 'courier_assigned',
                'title' => 'Courier Assigned',
                'description' => 'Courier assigned for pickup',
                'is_completed' => in_array($this->status, ['out_for_delivery', 'delivered']),
                'is_current' => $this->status === 'out_for_delivery',
            ],
            [
                'key' => 'out_for_delivery',
                'title' => 'Out for Delivery',
                'description' => 'Courier en route to customer address',
                'is_completed' => $this->status === 'delivered',
                'is_current' => $this->status === 'out_for_delivery',
            ],
            [
                'key' => 'delivered',
                'title' => 'Delivered',
                'description' => 'Successfully handed over to customer',
                'is_completed' => $this->status === 'delivered',
                'is_current' => $this->status === 'delivered',
            ],
        ];

        // 4. Customer Info & Initials
        $customerName = $this->user?->name ?? 'Customer';
        $words = explode(' ', trim($customerName));
        $initials = '';
        foreach ($words as $w) {
            $initials .= strtoupper(substr($w, 0, 1));
            if (strlen($initials) >= 2) break;
        }

        // 5. Customer Delivery Address
        $isPickup = $this->delivery_type === 'pickup';
        $fullAddressString = 'Store Pickup';
        $addressObj = null;

        if (!$isPickup) {
            if ($this->address) {
                $parts = array_filter([$this->address->house_no, $this->address->street_address, $this->address->city, $this->address->state, $this->address->zip_code]);
                $fullAddressString = implode(', ', $parts);
                $addressObj = [
                    'house_no' => $this->address->house_no,
                    'street_address' => $this->address->street_address,
                    'city' => $this->address->city,
                    'state' => $this->address->state,
                    'zip_code' => $this->address->zip_code,
                    'landmark' => $this->address->landmark,
                ];
            } elseif (!empty($this->delivery_address_json)) {
                $fullAddressString = is_array($this->delivery_address_json)
                    ? ($this->delivery_address_json['street_address'] ?? implode(', ', $this->delivery_address_json))
                    : $this->delivery_address_json;
                $addressObj = is_array($this->delivery_address_json) ? $this->delivery_address_json : ['address' => $this->delivery_address_json];
            } else {
                $fullAddressString = 'Delivery address provided at checkout';
            }
        }

        // 6. Action buttons availability matching Figma UI
        $availableActions = match ($this->status) {
            'new' => ['accept', 'reject'],
            'preparing' => ['mark_ready', 'view_fulfillment'],
            'ready_for_pickup' => ['out_for_delivery'],
            'out_for_delivery' => ['mark_delivered', 'mark_delayed'],
            default => [],
        };

        // 7. Order Items Transformation
        $items = [];
        if ($this->relationLoaded('items')) {
            $items = $this->items->map(function ($item) {
                $rawImg = $item->image ?: ($item->product?->images[0] ?? null);
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'name' => $item->product_name ?: ($item->product?->name ?? 'Product'),
                    'variant_flavor' => $item->variant_flavor,
                    'variant_size' => $item->variant_size,
                    'quantity' => (int) $item->quantity,
                    'formatted_quantity' => "Qty: {$item->quantity}",
                    'price' => (float) $item->price,
                    'formatted_price' => '$' . number_format($item->price, 2),
                    'total' => (float) $item->total,
                    'formatted_total' => '$' . number_format($item->total, 2),
                    'image' => format_media_url($rawImg, 'products'),
                ];
            })->toArray();
        }

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'status_label' => $statusLabel,
            'status_color' => $statusColor,
            'time_ago' => $this->created_at?->diffForHumans(),
            'stepper' => $stepper,
            'fulfillment_tracking' => [
                'badge' => $fulfillmentBadge,
                'steps' => $timelineSteps,
            ],
            'customer' => [
                'id' => $this->user?->id,
                'name' => $customerName,
                'initials' => $initials ?: 'CU',
                'avatar' => format_media_url($this->user?->avatar, 'avatars'),
                'phone' => $this->user?->phone_number,
                'email' => $this->user?->email,
                'delivery_type' => $this->delivery_type ?: 'delivery',
                'delivery_type_label' => $isPickup ? 'Store Pickup' : 'Delivery order',
                'address' => $fullAddressString,
                'address_details' => $addressObj,
            ],
            'items' => $items,
            'items_count' => count($items),
            'pricing' => [
                'subtotal' => (float) $this->subtotal,
                'formatted_subtotal' => '$' . number_format($this->subtotal, 2),
                'delivery_fee' => (float) $this->delivery_fee,
                'formatted_delivery_fee' => '$' . number_format($this->delivery_fee, 2),
                'tax' => (float) $this->tax,
                'formatted_tax' => '$' . number_format($this->tax, 2),
                'discount' => (float) ($this->discount ?? 0),
                'formatted_discount' => '$' . number_format($this->discount ?? 0, 2),
                'total' => (float) $this->total,
                'formatted_total' => '$' . number_format($this->total, 2),
            ],
            'payment' => [
                'method' => $this->payment_method,
                'method_label' => $this->payment_method === 'cash_on_delivery' ? 'Cash on Delivery' : ucfirst($this->payment_method ?? 'Card'),
                'status' => $this->payment_status,
                'status_label' => ucfirst($this->payment_status ?? 'Pending'),
            ],
            'notes' => $this->notes,
            'rejection_reason' => $this->rejection_reason,
            'available_actions' => $availableActions,
            'placed_at' => $this->placed_at?->toISOString() ?? $this->created_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
