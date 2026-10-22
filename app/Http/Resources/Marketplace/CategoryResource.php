<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'icon' => format_media_url($this->icon, 'categories'),
            'order_column' => $this->order_column,
            'products_count' => (int) ($this->products_count ?? 0),
        ];
    }
}
