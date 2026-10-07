<?php

namespace App\Http\Resources\Pet;



use App\Http\Resources\UserResource;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'name' => $this->name,
            'breed' => $this->breed,
            'age_years' => $this->age_years,
            'age_months' => $this->age_months,
            'weight' => (float) $this->weight,
            'bio' => $this->bio,
            'traits' => $this->traits,
            'personality' => $this->personality,
            'energy_level' => $this->energy_level,
            'training_level' => $this->training_level,
            'is_vaccinated' => (bool) $this->is_vaccinated,
            'gender' => $this->gender,
            'photo' => $this->photo ? url('storage/' . $this->photo) : null,
            'distance' => $this->when(isset($this->distance), function() { return round((float) $this->distance, 1); }),
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}





