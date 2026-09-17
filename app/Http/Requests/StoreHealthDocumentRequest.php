<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHealthDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // handled by controller policy
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // max 5MB
        ];
    }
}
