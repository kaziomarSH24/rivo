<?php

namespace App\Http\Resources\Appointment;



use App\Http\Resources\Pet\PetResource;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'pet_id' => $this->pet_id,
            'title' => $this->title,
            'type' => $this->type,
            'vet_name' => $this->vet_name,
            'location' => $this->location,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'datetime' => $this->datetime,
            'reminder' => $this->reminder,
            'status' => $this->status,
            'pet' => new PetResource($this->whenLoaded('pet')),
            'notes' => AppointmentNoteResource::collection($this->whenLoaded('notes')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}



