<?php

namespace App\Services\Social;

use App\Models\Post;
use App\Models\Reaction;
use App\Models\WalkSession;
use App\Services\BaseService;
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
        return ['user', 'pet', 'media', 'comments', 'likes', 'reactions', 'postable'];
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
                'title' => $data['title'] ?? null,
                'content' => $data['content'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'type' => $type,
                'location' => $data['location'] ?? null,
                'visibility' => $data['visibility'] ?? 'public',
                'hide_like_count' => $data['hide_like_count'] ?? false,
                'hide_share_count' => $data['hide_share_count'] ?? false,
                'turn_off_commenting' => $data['turn_off_commenting'] ?? false,
                'reaction_counts' => null,
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

            return $post->load(['user', 'pet', 'media', 'postable', 'reactions']);
        });
    }

    public function updatePost(Post $post, array $data, $mediaFiles = [])
    {
        return DB::transaction(function () use ($post, $data, $mediaFiles) {
            $fillable = [
                'title',
                'content',
                'metadata',
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

            return $post->load(['user', 'pet', 'media', 'postable', 'reactions']);
        });
    }

    public function toggleReaction($model, int $userId, string $reactionType = 'bone')
    {
        $allowed = ['bone', 'fire', 'paw', 'heart', 'clap'];
        $reactionType = in_array(strtolower($reactionType), $allowed) ? strtolower($reactionType) : 'bone';

        $existing = Reaction::where('user_id', $userId)
            ->where('reactable_type', get_class($model))
            ->where('reactable_id', $model->id)
            ->first();

        if ($existing) {
            if ($existing->reaction_type === $reactionType) {
                // Same reaction tapped again -> unreact
                $existing->delete();
                $status = 'unreacted';
                $userReaction = null;
            } else {
                // Different reaction tapped -> switch reaction (like Facebook)
                $existing->update(['reaction_type' => $reactionType]);
                $status = 'updated';
                $userReaction = $reactionType;
            }
        } else {
            // First time reaction
            Reaction::create([
                'user_id' => $userId,
                'reactable_type' => get_class($model),
                'reactable_id' => $model->id,
                'reaction_type' => $reactionType,
            ]);
            $status = 'reacted';
            $userReaction = $reactionType;
        }

        // Recalculate summary counts for the model
        $rawCounts = Reaction::where('reactable_type', get_class($model))
            ->where('reactable_id', $model->id)
            ->selectRaw('reaction_type, count(*) as count')
            ->groupBy('reaction_type')
            ->pluck('count', 'reaction_type')
            ->toArray();

        $counts = [
            'bone' => (int) ($rawCounts['bone'] ?? 0),
            'fire' => (int) ($rawCounts['fire'] ?? 0),
            'paw' => (int) ($rawCounts['paw'] ?? 0),
            'heart' => (int) ($rawCounts['heart'] ?? 0),
            'clap' => (int) ($rawCounts['clap'] ?? 0),
        ];

        $totalLikes = array_sum($counts);

        if ($model instanceof Post) {
            $model->update([
                'reaction_counts' => $counts,
                'likes_count' => $totalLikes,
            ]);
        }

        return [
            'status' => $status,
            'user_reaction' => $userReaction,
            'is_liked' => $userReaction !== null,
            'likes_count' => $totalLikes,
            'reactions' => $counts,
        ];
    }

    public function toggleLike(Post $post, $userId)
    {
        return $this->toggleReaction($post, $userId, 'bone');
    }
}
