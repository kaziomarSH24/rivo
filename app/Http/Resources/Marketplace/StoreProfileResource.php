<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reviewsSummary = $this->resource['reviews_summary'];
        if (isset($reviewsSummary['breakdown'])) {
            $reviewsSummary['breakdown'] = (object) $reviewsSummary['breakdown'];
        }

        return [
            'store' => new StoreCardResource($this->resource['store']),
            'categories' => CategoryResource::collection($this->resource['categories']),
            'reviews_summary' => $reviewsSummary,
            'products' => $this->resource['products'],
        ];
    }
}
