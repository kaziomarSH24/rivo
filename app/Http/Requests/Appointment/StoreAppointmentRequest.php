<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pet_id' => 'required|exists:pets,id',
            'title' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'vet_name' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'datetime' => 'required|date',
            'reminder' => 'required|in:1_hour,1_day,1_week,none',
            'notes' => 'nullable|string',
        ];
    }
}
