<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorProductCatalogResource extends JsonResource
{
    /**
     * Transform the catalog result (counters + paginator) into JSON matching Figma Products screen.
     */
    public function toArray(Request $request): array
    {
        $counters = $this->resource['counters'] ?? [
            'all' => 0,
            'active' => 0,
            'draft' => 0,
            'out_of_stock' => 0,
        ];

        $paginator = $this->resource['paginator'];

        return [
            'counters' => $counters,
            'current_page' => $paginator->currentPage(),
            'data' => VendorProductCardResource::collection($paginator->getCollection())->resolve(),
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
