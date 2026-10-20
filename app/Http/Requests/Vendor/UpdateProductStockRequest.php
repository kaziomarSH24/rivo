<?php

namespace App\Http\Requests\Vendor;

use App\Http\Requests\BaseRequest;

class UpdateProductStockRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'stock' => 'required|integer|min:0',
        ];
    }
}