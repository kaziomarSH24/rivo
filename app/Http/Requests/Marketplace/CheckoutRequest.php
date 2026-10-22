<?php

namespace App\Http\Requests\Marketplace;

use App\Http\Requests\BaseRequest;

class CheckoutRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'address_id' => 'nullable|exists:user_addresses,id',
            'delivery_address' => 'required_without:address_id|nullable|array',
            'delivery_address.street_address' => 'required_with:delivery_address|string|max:255',
            'delivery_address.house_no' => 'nullable|string|max:100',
            'delivery_address.landmark' => 'nullable|string|max:255',
            'delivery_type' => 'nullable|string|in:express,standard,pickup',
            'payment_method' => 'nullable|string|in:mastercard,visa,card,apple_pay,google_pay,stripe,cash_on_delivery,cash,cod',
            'payment_method_id' => 'nullable|string|max:255', // Stripe pm_... for saved cards
            'notes' => 'nullable|string|max:500',
        ];
    }
}