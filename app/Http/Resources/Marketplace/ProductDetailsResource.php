<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reviewsSummary = $this->resource['reviews_summary'];
        if (isset($reviewsSummary['breakdown'])) {
            $reviewsSummary['breakdown'] = (object) $reviewsSummary['breakdown'];
        }

        return [
            'product' => new ProductCardResource($this->resource['product']),
            'reviews_summary' => $reviewsSummary,
        ];
    }
}
