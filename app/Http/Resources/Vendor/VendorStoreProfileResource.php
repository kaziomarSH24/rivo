<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorStoreProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'owner_name' => $this->owner_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'street_address' => $this->street_address,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'logo' => format_media_url($this->logo, 'stores/logos'),
            'banner' => format_media_url($this->banner, 'stores/banners'),
            'rating' => (float) ($this->rating ?? 5.00),
            'reviews_count' => (int) ($this->reviews_count ?? 0),
            'fulfillment_rate' => (float) ($this->fulfillment_rate ?? 100.00),
            'avg_prep_time_minutes' => (int) ($this->avg_prep_time_minutes ?? 15),
            'delivery_fee' => (float) ($this->delivery_fee ?? 0.00),
            'delivery_time_text' => $this->delivery_time_text ?: '15-20 min',
            'is_verified' => (bool) $this->is_verified,
            'verification_status' => $this->verification_status ?: 'pending',
            'is_live' => (bool) $this->is_live,
            'documents' => StoreDocumentResource::collection($this->whenLoaded('documents')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}