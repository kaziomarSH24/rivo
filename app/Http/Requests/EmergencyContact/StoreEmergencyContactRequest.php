<?php

namespace App\Http\Requests\EmergencyContact;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmergencyContactRequest extends FormRequest
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
        return [
            'type' => [
                'required',
                'string',
                Rule::in(['primary', 'secondary']),
                // A user can only have one primary and one secondary contact
                Rule::unique('emergency_contacts')->where(function ($query) {
                    return $query->where('user_id', $this->user()->id);
                })
            ],
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'notes' => 'nullable|string|max:1000',
        ];
    }
    
    public function messages(): array
    {
        return [
            'type.unique' => 'You already have a ' . $this->type . ' emergency contact saved.',
        ];
    }
}
