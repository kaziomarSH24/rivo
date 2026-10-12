<?php

namespace App\Http\Requests\Social;

use App\Http\Requests\BaseRequest;

class UpdatePostRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|nullable|string|max:255',
            'content' => 'sometimes|nullable|string|max:500',
            'metadata' => 'sometimes|nullable|array',
            'location' => 'sometimes|nullable|string|max:255',
            'visibility' => 'sometimes|in:public,followers,private',
            'hide_like_count' => 'sometimes|boolean',
            'hide_share_count' => 'sometimes|boolean',
            'turn_off_commenting' => 'sometimes|boolean',
            'remove_media_ids' => 'sometimes|nullable|array',
            'remove_media_ids.*' => 'integer|exists:post_media,id',
            'media.*' => 'sometimes|file|mimes:jpeg,png,jpg,gif,mp4,mov|max:20480',
        ];
    }

    public function messages(): array
    {
        return [
            'content.max' => 'Caption cannot exceed 500 characters.',
            'visibility.in' => 'Visibility must be either public, followers, or private.',
        ];
    }
}
