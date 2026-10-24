<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorOrderQueueResource extends JsonResource
{
    /**
     * Transform the queue data containing live tab counters and paginated order cards.
     */
    public function toArray(Request $request): array
    {
        $counters = $this->resource['counters'] ?? [];
        $paginator = $this->resource['paginator'];

        return [
            'counters' => [
                'all' => (int) ($counters['all'] ?? 0),
                'today' => (int) ($counters['today'] ?? 0),
                'new' => (int) ($counters['new'] ?? 0),
                'preparing' => (int) ($counters['preparing'] ?? 0),
                'ready' => (int) ($counters['ready'] ?? ($counters['ready_for_pickup'] ?? 0)),
                'out_for_delivery' => (int) ($counters['out_for_delivery'] ?? 0),
                'delivered' => (int) ($counters['delivered'] ?? 0),
                'cancelled' => (int) ($counters['cancelled'] ?? 0),
            ],
            'current_page' => $paginator->currentPage(),
            'data' => VendorOrderCardResource::collection($paginator->items()),
            'first_page_url' => $paginator->url(1),
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'last_page_url' => $paginator->url($paginator->lastPage()),
            'next_page_url' => $paginator->nextPageUrl(),
            'path' => $paginator->path(),
            'per_page' => $paginator->perPage(),
            'prev_page_url' => $paginator->previousPageUrl(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ];
    }
}
