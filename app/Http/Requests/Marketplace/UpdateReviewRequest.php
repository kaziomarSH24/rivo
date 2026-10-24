<?php

namespace App\Http\Requests\Marketplace;

use App\Http\Requests\BaseRequest;

class UpdateReviewRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'is_anonymous' => 'nullable|boolean',
            'photos' => 'nullable',
            'photos.*' => 'image|max:5120',
            'remove_photos' => 'nullable|array',
            'remove_photos.*' => 'string',
        ];
    }
}