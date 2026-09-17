<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCareTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|string|in:food,water,medicine,sleep,walk',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'frequency' => 'nullable|string',
            'preferred_time' => 'nullable|date_format:H:i',
            'is_reminder_on' => 'boolean'
        ];
    }
}
