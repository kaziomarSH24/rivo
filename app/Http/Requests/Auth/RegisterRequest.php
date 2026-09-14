<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;


class RegisterRequest extends BaseRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required_without:phone_number|nullable|string|email|max:255|unique:users',
            'phone_number' => 'required_without:email|nullable|string|max:20|unique:users',
            'country_code' => 'nullable|string|max:5',
            'user_type' => 'nullable|string',
            'password' => 'required|string|min:8|confirmed',
            'fcm_token' => 'sometimes|string|nullable',
        ];
    }
}
