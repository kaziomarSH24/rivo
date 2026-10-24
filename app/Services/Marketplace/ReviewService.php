<?php

namespace App\Services\Marketplace;

use App\Http\Resources\Marketplace\ReviewResource;
use App\Models\Order;
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

        $orderId = !empty($data['order_id']) ? (int) $data['order_id'] : null;

        if ($orderId) {
            $order = Order::where('id', $orderId)->first();
            if (!$order || $order->user_id !== $user->id) {
                throw new \Exception('The specified order does not exist or does not belong to your account.');
            }

            if ($order->status !== 'delivered') {
                throw new \Exception("Reviews can only be submitted for delivered orders. This order is currently '{$order->status}'.");
            }

            if ($type === 'product') {
                $hasItem = $order->items()->where('product_id', $model->id)->exists();
                if (!$hasItem) {
                    throw new \Exception('You can only review products that were purchased in this order.');
                }
            } elseif ($type === 'store') {
                if ($order->store_id !== $model->id) {
                    throw new \Exception('This order was not placed at the specified store.');
                }
            }

            // Prevent duplicate review for the same order and item
            $existingReview = $model->reviews()
                ->where('user_id', $user->id)
                ->where('order_id', $order->id)
                ->exists();

            if ($existingReview) {
                throw new \Exception('You have already submitted a review for this item from this order.');
            }
        } else {
            // If order_id was not explicitly passed, attempt to link with user's delivered order for this item if one exists
            if ($type === 'product') {
                $deliveredOrder = Order::where('user_id', $user->id)
                    ->where('status', 'delivered')
                    ->whereHas('items', fn($q) => $q->where('product_id', $model->id))
                    ->latest()
                    ->first();
                if ($deliveredOrder) {
                    $orderId = $deliveredOrder->id;
                }
            } elseif ($type === 'store') {
                $deliveredOrder = Order::where('user_id', $user->id)
                    ->where('status', 'delivered')
                    ->where('store_id', $model->id)
                    ->latest()
                    ->first();
                if ($deliveredOrder) {
                    $orderId = $deliveredOrder->id;
                }
            }

            if ($orderId) {
                $existingReview = $model->reviews()
                    ->where('user_id', $user->id)
                    ->where('order_id', $orderId)
                    ->exists();

                if ($existingReview) {
                    throw new \Exception('You have already submitted a review for this item.');
                }
            }
        }

        $uploadedPhotos = [];
        if ($request->hasFile('photos')) {
            $photos = $request->file('photos');
            if (is_array($photos)) {
                foreach ($photos as $photo) {
                    if ($photo && $photo->isValid()) {
                        $uploadedPhotos[] = $photo->store('reviews', 'public');
                    }
                }
            } elseif ($photos && $photos->isValid()) {
                $uploadedPhotos[] = $photos->store('reviews', 'public');
            }
        } elseif ($request->hasFile('photo')) {
            $photo = $request->file('photo');
            if ($photo && $photo->isValid()) {
                $uploadedPhotos[] = $photo->store('reviews', 'public');
            }
        }

        $rating = max(1, min(5, (int) $data['rating']));

        $review = $model->reviews()->create([
            'user_id' => $user->id,
            'order_id' => $orderId,
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

        return [
            'rating' => $avgRating,
            'reviews_count' => $totalCount,
            'breakdown' => (object) $breakdown,
            'reviews' => [
                'current_page' => $reviewsList->currentPage(),
                'data' => ReviewResource::collection($reviewsList->getCollection())->resolve(),
                'per_page' => $reviewsList->perPage(),
                'total' => $reviewsList->total(),
                'last_page' => $reviewsList->lastPage(),
            ],
        ];
    }
}