<?php

namespace App\Services\Social;

use App\Models\Post;
use App\Models\PostLike;
use App\Services\BaseService;
use App\Notifications\PostLiked;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PostService extends BaseService
{
    protected string $modelClass = Post::class;

    protected function getAllowedFilters(): array
    {
        return ['type', 'user_id', 'pet_id'];
    }

    protected function getAllowedIncludes(): array
    {
        return ['user', 'pet', 'media', 'comments', 'likes'];
    }

    protected function getAllowedSorts(): array
    {
        return ['created_at', 'likes_count', 'comments_count'];
    }

    public function createPost(array $data, $mediaFiles = [])
    {
        return DB::transaction(function () use ($data, $mediaFiles) {
            $post = Post::create([
                'user_id' => $data['user_id'],
                'pet_id' => $data['pet_id'] ?? null,
                'content' => $data['content'] ?? null,
                'type' => $data['type'] ?? 'general',
            ]);

            if (!empty($mediaFiles)) {
                foreach ($mediaFiles as $index => $file) {
                    $path = $file->store('posts', 'public');
                    $type = str_starts_with($file->getMimeType(), 'video') ? 'video' : 'image';
                    $post->media()->create([
                        'media_path' => $path,
                        'media_type' => $type,
                        'order' => $index
                    ]);
                }
            }

            return $post->load(['user', 'pet', 'media']);
        });
    }

    public function toggleLike(Post $post, $userId)
    {
        $like = PostLike::where('post_id', $post->id)->where('user_id', $userId)->first();
        
        if ($like) {
            $like->delete();
            $post->decrement('likes_count');
            return ['status' => 'unliked', 'likes_count' => $post->likes_count];
        } else {
            PostLike::create(['post_id' => $post->id, 'user_id' => $userId]);
            $post->increment('likes_count');
            
            // Notify if the user is not the owner
            if ($post->user_id !== $userId) {
                // $post->user->notify(new PostLiked($post, $userId));
            }
            return ['status' => 'liked', 'likes_count' => $post->likes_count];
        }
    }
}
