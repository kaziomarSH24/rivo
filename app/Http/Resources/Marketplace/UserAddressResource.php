<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserAddressResource extends JsonResource
{
    /**
     * Transform the resource into an array matching Figma Delivery Address.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'street_address' => $this->street_address,
            'house_no' => $this->house_no,
            'landmark' => $this->landmark,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'is_default' => (bool) $this->is_default,
            'formatted_address' => trim(implode(', ', array_filter([
                $this->house_no,
                $this->street_address,
                $this->landmark,
                $this->city,
                $this->state,
                $this->zip_code,
            ]))),
        ];
    }
}
