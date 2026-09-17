<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PetWeightLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'weight_kg' => $this->weight_kg,
            'logged_at' => $this->logged_at->format('d M Y'),
            'logged_at_iso' => $this->logged_at->toIso8601String(),
        ];
    }
}
