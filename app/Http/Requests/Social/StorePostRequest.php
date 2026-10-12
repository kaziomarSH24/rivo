<?php

namespace App\Http\Requests\Social;

use App\Http\Requests\BaseRequest;

class StorePostRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|nullable|string|max:255',
            'content' => 'required_without_all:media,walk_session_id,metadata|nullable|string',
            'metadata' => 'sometimes|nullable|array',
            'type' => 'sometimes|in:general,lost_pet,milestone,walk_summary,training_achievement,achievement,training',
            'pet_id' => 'sometimes|nullable|exists:pets,id',
            'walk_session_id' => 'required_if:type,walk_summary|nullable|exists:walk_sessions,id',
            'location' => 'sometimes|nullable|string|max:255',
            'visibility' => 'sometimes|in:public,followers,private',
            'media.*' => 'sometimes|file|mimes:jpeg,png,jpg,gif,mp4,mov|max:20480',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required_without_all' => 'Post content, media, or walk session is required.',
            'walk_session_id.required_if' => 'A walk session is required to share a walk summary.',
            'walk_session_id.exists' => 'The selected walk session was not found.',
        ];
    }
}
