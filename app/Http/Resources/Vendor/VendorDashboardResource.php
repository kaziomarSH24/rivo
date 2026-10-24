<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hour = (int) now()->format('H');
        $greeting = match (true) {
            $hour < 12 => 'Good morning 👋',
            $hour < 17 => 'Good afternoon 👋',
            default => 'Good evening 👋',
        };

        $store = $this->resource['store'];

        return [
            'greeting' => $greeting,
            'store' => [
                'id' => $store['id'],
                'name' => $store['name'],
                'logo' => $store['logo'],
                'is_live' => (bool) $store['is_live'],
                'is_verified' => (bool) $store['is_verified'],
                'rating' => (float) ($store['rating'] ?? 5.0),
                'reviews_count' => (int) ($store['reviews_count'] ?? 0),
                'fulfillment_rate' => (float) ($store['fulfillment_rate'] ?? 100.0),
                'avg_prep_time_minutes' => (int) ($store['avg_prep_time_minutes'] ?? 15),
            ],
            'order_overview' => $this->resource['order_overview'],
            'today_sales' => $this->resource['today_sales'],
            'store_performance' => $this->resource['store_performance'] ?? [
                'rating' => (float) ($store['rating'] ?? 5.0),
                'reviews_count' => (int) ($store['reviews_count'] ?? 0),
                'success_rate' => ((float) ($store['fulfillment_rate'] ?? 100.0)) . '%',
                'avg_time' => '< ' . ((int) ($store['avg_prep_time_minutes'] ?? 15)) . 'm',
            ],
            'low_stock_alerts' => $this->resource['low_stock_alerts'],
            'recent_orders' => $this->resource['recent_orders'],
        ];
    }
}