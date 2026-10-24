<?php

namespace App\Http\Requests\Vendor;

use App\Http\Requests\BaseRequest;

class VendorAnalyticsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'period' => 'nullable|string|in:today,week,month',
        ];
    }
}