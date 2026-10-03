<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLostPetReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in controller/policy
        return true;
    }

    public function rules(): array
    {
        return [
            'last_seen_date' => 'required|date',
            'last_seen_time' => 'required|date_format:H:i',
            'last_seen_location' => 'required|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'additional_info' => 'nullable|string',
            'reward_amount' => 'nullable|numeric|min:0',
            'last_seen_photo' => 'nullable|image|max:5120',
        ];
    }
}
