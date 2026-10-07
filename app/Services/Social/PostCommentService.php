<?php

namespace App\Services\Social;

use App\Models\Post;
use App\Models\PostComment;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

class PostCommentService extends BaseService
{
    protected string $modelClass = PostComment::class;

    protected function getAllowedFilters(): array
    {
        return ['post_id', 'user_id'];
    }

    protected function getAllowedIncludes(): array
    {
        return ['user', 'post'];
    }

    protected function getAllowedSorts(): array
    {
        return ['created_at'];
    }

    public function createComment(Post $post, array $data)
    {
        return DB::transaction(function () use ($post, $data) {
            $comment = $post->comments()->create([
                'user_id' => $data['user_id'],
                'content' => $data['content']
            ]);

            $post->increment('comments_count');

            return $comment->load('user');
        });
    }
}
