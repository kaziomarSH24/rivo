<?php

namespace App\Http\Requests\Marketplace;

use App\Http\Requests\BaseRequest;

class StoreReviewRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'reviewable_type' => 'required|string|in:product,store',
            'reviewable_id' => 'required|integer',
            'order_id' => 'nullable|integer|exists:orders,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'is_anonymous' => 'nullable|boolean',
            'photos' => 'nullable',
            'photos.*' => 'image|max:5120',
        ];
    }
}