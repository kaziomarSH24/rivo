<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePetWeightLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // handled by controller policy
    }

    public function rules(): array
    {
        return [
            'weight_kg' => 'required|numeric|min:0.1|max:200',
        ];
    }
}
