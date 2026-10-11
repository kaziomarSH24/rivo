<?php

namespace App\Http\Requests\Social;

use App\Http\Requests\BaseRequest;

class StorePostCommentRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:post_comments,id',
        ];
    }
}
