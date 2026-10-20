<?php

namespace App\Http\Requests\Marketplace;

use App\Http\Requests\BaseRequest;

class AddToCartRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'nullable|integer|min:1',
        ];
    }
}