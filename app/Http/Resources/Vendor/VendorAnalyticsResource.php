<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorAnalyticsResource extends JsonResource
{
    /**
     * Transform the resource into an array matching Figma "Analysis" screen.
     */
    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : $this->resource->toArray();

        return [
            'period' => $data['period'] ?? 'week',
            'headline' => $data['headline'] ?? [],
            'chart' => $data['chart'] ?? ($data['sales_chart'] ?? []),
            'metrics' => $data['metrics'] ?? [],
            'best_sellers' => $data['best_sellers'] ?? [],

            // Backwards compatibility / flat fields:
            'total_sales' => (float) ($data['total_sales'] ?? 0.0),
            'total_orders' => (int) ($data['total_orders'] ?? 0),
            'completed_orders' => (int) ($data['completed_orders'] ?? 0),
            'cancelled_orders' => (int) ($data['cancelled_orders'] ?? 0),
            'completion_rate' => (float) ($data['completion_rate'] ?? 100.0),
            'avg_order_value' => (float) ($data['avg_order_value'] ?? 0.0),
            'sales_growth_percentage' => (float) ($data['sales_growth_percentage'] ?? 0.0),
            'sales_growth_text' => $data['sales_growth_text'] ?? ($data['headline']['growth_label'] ?? ''),
            'sales_chart' => $data['chart'] ?? ($data['sales_chart'] ?? []),
        ];
    }
}
