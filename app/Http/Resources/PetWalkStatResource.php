<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PetWalkStatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pet_id' => $this->pet_id,
            'daily_distance_goal_km' => (float) $this->daily_distance_goal_km,
            'total_xp' => $this->total_xp,
            'current_level' => $this->current_level,
            'current_streak_days' => $this->current_streak_days,
            'longest_streak_days' => $this->longest_streak_days,
            'last_walk_date' => $this->last_walk_date ? $this->last_walk_date->toDateString() : null,
            'created_at' => $this->created_at,
        ];
    }
}
