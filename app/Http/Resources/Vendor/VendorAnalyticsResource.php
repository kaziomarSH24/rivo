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
            'headline' => $data['headline'] ?? [
                'label' => 'Total Sales (' . ucfirst($data['period'] ?? 'week') . ')',
                'amount' => (float) ($data['total_sales'] ?? 0.0),
                'formatted_amount' => '$' . number_format((float) ($data['total_sales'] ?? 0.0), 2),
                'growth_percentage' => (float) ($data['sales_growth_percentage'] ?? 0.0),
                'growth_direction' => ($data['sales_growth_percentage'] ?? 0.0) >= 0 ? 'up' : 'down',
                'growth_label' => $data['sales_growth_text'] ?? '+0.0% vs last week',
            ],
            'chart' => $data['chart'] ?? ($data['sales_chart'] ?? []),
            'metrics' => $data['metrics'] ?? [
                'total_orders' => [
                    'label' => 'Total Orders',
                    'count' => (int) ($data['total_orders'] ?? 0),
                    'subtext' => '+0 today',
                    'icon' => 'box',
                ],
                'completed_orders' => [
                    'label' => 'Completed',
                    'count' => (int) ($data['completed_orders'] ?? 0),
                    'rate' => (float) ($data['completion_rate'] ?? 100.0),
                    'subtext' => ($data['completion_rate'] ?? 100.0) . '% rate',
                    'icon' => 'check',
                ],
                'cancelled_orders' => [
                    'label' => 'Cancelled',
                    'count' => (int) ($data['cancelled_orders'] ?? 0),
                    'rate' => round(100.0 - (float) ($data['completion_rate'] ?? 100.0), 1),
                    'subtext' => round(100.0 - (float) ($data['completion_rate'] ?? 100.0), 1) . '% rate',
                    'icon' => 'cross',
                ],
                'avg_order_value' => [
                    'label' => 'Avg Order Value',
                    'amount' => (float) ($data['avg_order_value'] ?? 0.0),
                    'formatted_amount' => '$' . number_format((float) ($data['avg_order_value'] ?? 0.0), 2),
                    'growth' => '+$0 vs last wk',
                    'subtext' => '+$0 vs last wk',
                    'icon' => 'money',
                ],
            ],
            'best_sellers' => $data['best_sellers'] ?? [],

            // Backwards compatibility / flat fields:
            'total_sales' => (float) ($data['total_sales'] ?? 0.0),
            'total_orders' => (int) ($data['total_orders'] ?? 0),
            'completed_orders' => (int) ($data['completed_orders'] ?? 0),
            'cancelled_orders' => (int) ($data['cancelled_orders'] ?? 0),
            'completion_rate' => (float) ($data['completion_rate'] ?? 100.0),
            'avg_order_value' => (float) ($data['avg_order_value'] ?? 0.0),
            'sales_growth_percentage' => (float) ($data['sales_growth_percentage'] ?? 0.0),
            'sales_growth_text' => $data['sales_growth_text'] ?? '+0.0% vs last week',
            'sales_chart' => $data['chart'] ?? ($data['sales_chart'] ?? []),
        ];
    }
}
