<?php

namespace App\Http\Requests\Vendor;

use App\Http\Requests\BaseRequest;

class UpdateVendorStoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'type' => 'nullable|string|in:pet_shop,veterinary,grooming,pharmacy',
            'owner_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'street_address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'delivery_fee' => 'nullable|numeric|min:0',
            'delivery_time_text' => 'nullable|string|max:100',
            'is_live' => 'nullable|boolean',
            'logo' => 'nullable|image|max:5120',
            'banner' => 'nullable|image|max:10240',
        ];
    }
}