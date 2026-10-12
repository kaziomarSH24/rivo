<?php

namespace App\Services\Social;

use App\Models\Post;
use App\Models\Reaction;
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
            ->where('reaction_type', $reactionType)
            ->first();

        if ($existing) {
            $existing->delete();
            $status = 'unreacted';
        } else {
            Reaction::create([
                'user_id' => $userId,
                'reactable_type' => get_class($model),
                'reactable_id' => $model->id,
                'reaction_type' => $reactionType,
            ]);
            $status = 'reacted';
        }

        // Recalculate summary counts for the model
        $counts = Reaction::where('reactable_type', get_class($model))
            ->where('reactable_id', $model->id)
            ->selectRaw('reaction_type, count(*) as count')
            ->groupBy('reaction_type')
            ->pluck('count', 'reaction_type')
            ->toArray();

        $totalLikes = array_sum($counts);

        if ($model instanceof Post) {
            $model->update([
                'reaction_counts' => $counts,
                'likes_count' => $totalLikes,
            ]);
        }

        $userReactions = Reaction::where('reactable_type', get_class($model))
            ->where('reactable_id', $model->id)
            ->where('user_id', $userId)
            ->pluck('reaction_type')
            ->toArray();

        $reactionsPayload = [
            'total' => $totalLikes,
            'counts' => [
                'bone' => (int) ($counts['bone'] ?? 0),
                'fire' => (int) ($counts['fire'] ?? 0),
                'paw' => (int) ($counts['paw'] ?? 0),
                'heart' => (int) ($counts['heart'] ?? 0),
                'clap' => (int) ($counts['clap'] ?? 0),
            ],
            'user_reactions' => $userReactions,
            'user_has_boned' => in_array('bone', $userReactions),
            'user_has_fired' => in_array('fire', $userReactions),
            'user_has_pawed' => in_array('paw', $userReactions),
            'user_has_hearted' => in_array('heart', $userReactions),
            'user_has_clapped' => in_array('clap', $userReactions),
        ];

        return [
            'status' => $status,
            'reaction_type' => $reactionType,
            'counts' => $counts,
            'bones_count' => (int) ($counts['bone'] ?? 0),
            'fires_count' => (int) ($counts['fire'] ?? 0),
            'paws_count' => (int) ($counts['paw'] ?? 0),
            'hearts_count' => (int) ($counts['heart'] ?? 0),
            'claps_count' => (int) ($counts['clap'] ?? 0),
            'likes_count' => $totalLikes,
            'reactions' => $reactionsPayload,
            'user_reactions' => $userReactions,
            'user_has_boned' => in_array('bone', $userReactions),
            'user_has_fired' => in_array('fire', $userReactions),
            'user_has_pawed' => in_array('paw', $userReactions),
            'user_has_hearted' => in_array('heart', $userReactions),
            'user_has_clapped' => in_array('clap', $userReactions),
        ];
    }

    public function toggleLike(Post $post, $userId)
    {
        return $this->toggleReaction($post, $userId, 'bone');
    }
}
