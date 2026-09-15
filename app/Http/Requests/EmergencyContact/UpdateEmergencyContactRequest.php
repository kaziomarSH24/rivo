<?php

namespace App\Http\Requests\EmergencyContact;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmergencyContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $contactId = $this->route('emergency_contact')->id ?? null;

        return [
            'type' => [
                'sometimes',
                'string',
                Rule::in(['primary', 'secondary']),
                Rule::unique('emergency_contacts')->where(function ($query) {
                    return $query->where('user_id', $this->user()->id);
                })->ignore($contactId)
            ],
            'name' => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|string|max:20',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
