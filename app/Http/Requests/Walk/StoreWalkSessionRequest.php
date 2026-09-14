<?php

namespace App\Http\Requests\Walk;

use Illuminate\Foundation\Http\FormRequest;

class StoreWalkSessionRequest extends FormRequest
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
            'route_id' => 'nullable|exists:walk_routes,id',
            'distance_km' => 'required|numeric|min:0',
            'duration_seconds' => 'required|integer|min:0',
            'steps' => 'nullable|integer|min:0',
            'calories' => 'required|integer|min:0',
            'avg_speed_kmh' => 'nullable|numeric|min:0',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
            'route_coordinates' => 'nullable|array',
            'route_coordinates.*.lat' => 'required_with:route_coordinates|numeric',
            'route_coordinates.*.lng' => 'required_with:route_coordinates|numeric',
        ];
    }
}
