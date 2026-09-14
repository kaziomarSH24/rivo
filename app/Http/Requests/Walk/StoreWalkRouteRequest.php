<?php

namespace App\Http\Requests\Walk;

use Illuminate\Foundation\Http\FormRequest;

class StoreWalkRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if (is_string($this->route_coordinates)) {
            $decoded = json_decode($this->route_coordinates, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['route_coordinates' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'distance_km' => 'required|numeric|min:0',
            'est_duration_minutes' => 'nullable|integer|min:0',
            'route_coordinates' => 'nullable|array',
            'route_coordinates.*.lat' => 'required_with:route_coordinates|numeric',
            'route_coordinates.*.lng' => 'required_with:route_coordinates|numeric',
            'is_favorite' => 'nullable|boolean',
        ];
    }
}
