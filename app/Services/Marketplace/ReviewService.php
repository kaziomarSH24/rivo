<?php

namespace App\Services\Marketplace;

use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use App\Services\BaseService;
use App\Traits\FileUploadTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ReviewService extends BaseService
{
    use FileUploadTrait;

    protected string $modelClass = Review::class;

    protected function getAllowedFilters(): array
    {
        return ['user_id', 'rating', 'reviewable_type', 'reviewable_id'];
    }

    protected function getAllowedIncludes(): array
    {
        return ['user', 'order', 'reviewable'];
    }

    protected function getAllowedSorts(): array
    {
        return ['created_at', 'rating'];
    }

    /**
     * Submit a rating and review for Product or Store
     */
    public function submitReview(User $user, string $type, int $id, array $data, Request $request): Review
    {
        $model = $type === 'store' ? Store::findOrFail($id) : Product::findOrFail($id);

        $uploadedPhotos = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $uploadedPhotos[] = $photo->store('reviews', 'public');
            }
        } elseif ($request->hasFile('photo')) {
            $uploadedPhotos[] = $request->file('photo')->store('reviews', 'public');
        }

        $rating = max(1, min(5, (int) $data['rating']));

        $review = $model->reviews()->create([
            'user_id' => $user->id,
            'order_id' => $data['order_id'] ?? null,
            'rating' => $rating,
            'comment' => $data['comment'] ?? null,
            'photos' => $uploadedPhotos,
            'is_anonymous' => filter_var($data['is_anonymous'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ]);

        // Recalculate average rating and reviews_count via relation
        $avgRating = $model->reviews()->avg('rating') ?: 5.0;
        $reviewsCount = $model->reviews()->count();

        $model->rating = round($avgRating, 1);
        $model->reviews_count = $reviewsCount;
        $model->save();

        return $review->load(['user:id,name,avatar']);
    }

    /**
     * Get reviews and 5-star breakdown statistics for a Product or Store
     */
    public function getReviewsSummary(Model $model): array
    {
        $reviewsQuery = $model->reviews();

        $totalCount = (clone $reviewsQuery)->count();
        $avgRating = $totalCount > 0 ? round((clone $reviewsQuery)->avg('rating'), 1) : 5.0;

        // Star breakdown (1 to 5 stars)
        $breakdown = [];
        for ($star = 5; $star >= 1; $star--) {
            $starCount = (clone $reviewsQuery)->where('rating', $star)->count();
            $percentage = $totalCount > 0 ? round(($starCount / $totalCount) * 100) : 0;
            $breakdown[(string) $star] = [
                'star' => $star,
                'count' => $starCount,
                'percentage' => $percentage,
            ];
        }

        $reviewsList = (clone $reviewsQuery)
            ->with(['user:id,name,avatar'])
            ->latest()
            ->paginate(15);

        // Format reviews list cleanly with avatars and relative timestamps
        $formattedReviews = $reviewsList->getCollection()->map(function ($r) {
            $userName = $r->is_anonymous ? 'Anonymous User' : ($r->user?->name ?? 'Customer');
            $userAvatar = $r->is_anonymous ? null : format_media_url($r->user?->avatar, 'avatars');

            return [
                'id' => $r->id,
                'user' => [
                    'id' => $r->is_anonymous ? null : $r->user?->id,
                    'name' => $userName,
                    'avatar' => $userAvatar,
                ],
                'rating' => (int) $r->rating,
                'comment' => $r->comment,
                'photos' => array_map(fn($p) => format_media_url($p, 'reviews'), $r->photos ?? []),
                'created_at' => $r->created_at?->toIso8601String(),
                'created_at_human' => $r->created_at?->diffForHumans() ?? 'recently',
            ];
        });

        return [
            'rating' => $avgRating,
            'reviews_count' => $totalCount,
            'breakdown' => (object) $breakdown,
            'reviews' => [
                'current_page' => $reviewsList->currentPage(),
                'data' => $formattedReviews->values()->all(),
                'per_page' => $reviewsList->perPage(),
                'total' => $reviewsList->total(),
                'last_page' => $reviewsList->lastPage(),
            ],
        ];
    }
}