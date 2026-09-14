<?php

namespace App\Http\Requests\Pet;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'sometimes|required|string|in:Dog,Cat',
            'name' => 'sometimes|required|string|max:100',
            'breed' => 'nullable|string|max:100',
            'age_years' => 'nullable|integer|min:0',
            'age_months' => 'nullable|integer|min:0|max:11',
            'weight' => 'nullable|numeric|min:0',
            'gender' => 'nullable|in:Male,Female,Unknown',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }
}
