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

        $review = Review::create([
            'user_id' => $user->id,
            'order_id' => $data['order_id'] ?? null,
            'reviewable_type' => get_class($model),
            'reviewable_id' => $model->id,
            'rating' => $rating,
            'comment' => $data['comment'] ?? null,
            'photos' => $uploadedPhotos,
            'is_anonymous' => filter_var($data['is_anonymous'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ]);

        // Recalculate average rating and reviews_count
        $avgRating = Review::where('reviewable_type', get_class($model))
            ->where('reviewable_id', $model->id)
            ->avg('rating');

        $reviewsCount = Review::where('reviewable_type', get_class($model))
            ->where('reviewable_id', $model->id)
            ->count();

        $model->rating = round($avgRating, 2);
        $model->reviews_count = $reviewsCount;
        $model->save();

        return $review->load(['user:id,name,avatar']);
    }

    /**
     * Get reviews and 5-star breakdown statistics for a Product or Store
     */
    public function getReviewsSummary(Model $model): array
    {
        $reviewsQuery = Review::where('reviewable_type', get_class($model))
            ->where('reviewable_id', $model->id);

        $totalCount = (clone $reviewsQuery)->count();
        $avgRating = $totalCount > 0 ? round((clone $reviewsQuery)->avg('rating'), 1) : 5.0;

        // Star breakdown (1 to 5 stars)
        $breakdown = [];
        for ($star = 5; $star >= 1; $star--) {
            $starCount = (clone $reviewsQuery)->where('rating', $star)->count();
            $percentage = $totalCount > 0 ? round(($starCount / $totalCount) * 100) : 0;
            $breakdown[$star] = [
                'count' => $starCount,
                'percentage' => $percentage,
            ];
        }

        $reviewsList = (clone $reviewsQuery)
            ->with(['user:id,name,avatar'])
            ->latest()
            ->paginate(15);

        // Hide user name if anonymous
        $reviewsList->getCollection()->transform(function ($r) {
            if ($r->is_anonymous) {
                $r->user = [
                    'id' => null,
                    'name' => 'Anonymous User',
                    'avatar' => null,
                ];
            }
            return $r;
        });

        return [
            'rating' => $avgRating,
            'reviews_count' => $totalCount,
            'breakdown' => $breakdown,
            'reviews' => $reviewsList,
        ];
    }
}