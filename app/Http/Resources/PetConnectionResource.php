<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Pet\PetResource;

class PetConnectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pet_id' => $this->pet_id,
            'connected_pet_id' => $this->connected_pet_id,
            'status' => $this->status,
            'source' => $this->source,
            'pet' => new PetResource($this->whenLoaded('pet')),
            'connected_pet' => new PetResource($this->whenLoaded('connectedPet')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
