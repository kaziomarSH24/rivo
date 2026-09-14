<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalkSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pet_id' => $this->pet_id,
            'route_id' => $this->route_id,
            'distance_km' => (float) $this->distance_km,
            'duration_seconds' => $this->duration_seconds,
            'steps' => $this->steps,
            'calories' => $this->calories,
            'avg_speed_kmh' => (float) $this->avg_speed_kmh,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'route_coordinates' => $this->route_coordinates,
            'xp_earned' => $this->xp_earned,
            'route' => new WalkRouteResource($this->whenLoaded('route')),
            'pet' => new PetResource($this->whenLoaded('pet')),
            'created_at' => $this->created_at,
        ];
    }
}
