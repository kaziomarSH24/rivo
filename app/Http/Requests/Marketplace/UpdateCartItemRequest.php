<?php

namespace App\Http\Requests\Marketplace;

use App\Http\Requests\BaseRequest;

class UpdateCartItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'quantity' => 'required|integer|min:1',
        ];
    }
}