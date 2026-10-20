<?php

namespace App\Http\Requests\Marketplace;

use App\Http\Requests\BaseRequest;

class StoreAddressRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
                        'street_address' => 'required|string|max:255',
            'house_no' => 'nullable|string|max:100',
            'landmark' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'is_default' => 'nullable|boolean',
        ];
    }
}