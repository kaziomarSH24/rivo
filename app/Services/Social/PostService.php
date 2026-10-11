<?php

namespace App\Services\Social;

use App\Models\Post;
use App\Models\PostLike;
use App\Models\WalkSession;
use App\Services\BaseService;
use App\Notifications\PostLiked;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PostService extends BaseService
{
    protected string $modelClass = Post::class;

    protected function getAllowedFilters(): array
    {
        return ['type', 'user_id', 'pet_id', 'visibility'];
    }

    protected function getAllowedIncludes(): array
    {
        return ['user', 'pet', 'media', 'comments', 'likes', 'postable'];
    }

    protected function getAllowedSorts(): array
    {
        return ['created_at', 'likes_count', 'comments_count'];
    }

    public function createPost(array $data, $mediaFiles = [])
    {
        return DB::transaction(function () use ($data, $mediaFiles) {
            $postableType = null;
            $postableId = null;

            $type = $data['type'] ?? 'general';

            if (!empty($data['walk_session_id'])) {
                $type = 'walk_summary';
                $walkSession = WalkSession::findOrFail($data['walk_session_id']);
                $postableType = WalkSession::class;
                $postableId = $walkSession->id;

                if (empty($data['pet_id'])) {
                    $data['pet_id'] = $walkSession->pet_id;
                }
            } elseif (!empty($data['postable_type']) && !empty($data['postable_id'])) {
                $postableType = $data['postable_type'];
                $postableId = $data['postable_id'];
            }

            $post = Post::create([
                'user_id' => $data['user_id'],
                'pet_id' => $data['pet_id'] ?? null,
                'content' => $data['content'] ?? null,
                'type' => $type,
                'location' => $data['location'] ?? null,
                'visibility' => $data['visibility'] ?? 'public',
                'hide_like_count' => $data['hide_like_count'] ?? false,
                'hide_share_count' => $data['hide_share_count'] ?? false,
                'turn_off_commenting' => $data['turn_off_commenting'] ?? false,
                'postable_type' => $postableType,
                'postable_id' => $postableId,
            ]);

            if (!empty($mediaFiles)) {
                foreach ($mediaFiles as $index => $file) {
                    $path = $file->store('posts', 'public');
                    $mediaType = str_starts_with($file->getMimeType(), 'video') ? 'video' : 'image';
                    $post->media()->create([
                        'media_path' => $path,
                        'media_type' => $mediaType,
                        'order' => $index
                    ]);
                }
            }

            return $post->load(['user', 'pet', 'media', 'postable', 'likes']);
        });
    }

    public function updatePost(Post $post, array $data, $mediaFiles = [])
    {
        return DB::transaction(function () use ($post, $data, $mediaFiles) {
            $fillable = [
                'content',
                'location',
                'visibility',
                'hide_like_count',
                'hide_share_count',
                'turn_off_commenting',
            ];

            $updateData = array_intersect_key($data, array_flip($fillable));
            if (!empty($updateData)) {
                $post->update($updateData);
            }

            // Remove specific media files if requested
            if (!empty($data['remove_media_ids'])) {
                $mediaToRemove = $post->media()->whereIn('id', $data['remove_media_ids'])->get();
                foreach ($mediaToRemove as $media) {
                    Storage::disk('public')->delete($media->media_path);
                    $media->delete();
                }
            }

            // Upload new media files if provided
            if (!empty($mediaFiles)) {
                $highestOrder = (int) $post->media()->max('order');
                foreach ($mediaFiles as $index => $file) {
                    $path = $file->store('posts', 'public');
                    $mediaType = str_starts_with($file->getMimeType(), 'video') ? 'video' : 'image';
                    $post->media()->create([
                        'media_path' => $path,
                        'media_type' => $mediaType,
                        'order' => $highestOrder + $index + 1
                    ]);
                }
            }

            return $post->load(['user', 'pet', 'media', 'postable', 'likes']);
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
