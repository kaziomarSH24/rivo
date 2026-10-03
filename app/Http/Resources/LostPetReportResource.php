<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Pet\PetResource;
use Illuminate\Support\Facades\Storage;

class LostPetReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'last_seen_date' => $this->last_seen_date,
            'last_seen_time' => $this->last_seen_time,
            'last_seen_location' => $this->last_seen_location,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'additional_info' => $this->additional_info,
            'reward_amount' => $this->reward_amount ? (float) $this->reward_amount : null,
            'last_seen_photo_url' => $this->last_seen_photo_url,
            'created_at' => $this->created_at,
            'pet' => new PetResource($this->whenLoaded('pet')),
            'user' => [
                'id' => $this->whenLoaded('user') ? $this->user->id : null,
                'name' => $this->whenLoaded('user') ? $this->user->name : null,
                'email' => $this->whenLoaded('user') ? $this->user->email : null,
            ],
        ];
    }
}



