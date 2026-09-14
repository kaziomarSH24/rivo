<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalkRouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'distance_km' => (float) $this->distance_km,
            'est_duration_minutes' => $this->est_duration_minutes,
            'route_coordinates' => $this->route_coordinates,
            'is_favorite' => (bool) $this->is_favorite,
            'created_at' => $this->created_at,
        ];
    }
}
