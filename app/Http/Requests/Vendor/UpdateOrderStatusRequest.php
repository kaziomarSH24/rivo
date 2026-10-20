<?php

namespace App\Http\Requests\Vendor;

use App\Http\Requests\BaseRequest;

class UpdateOrderStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => 'required|in:preparing,ready_for_pickup,out_for_delivery,delivered,cancelled',
            'reason' => 'nullable|string|max:255',
        ];
    }
}