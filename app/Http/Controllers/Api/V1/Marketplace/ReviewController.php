<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\StoreReviewRequest;
use App\Http\Requests\Marketplace\UpdateReviewRequest;
use App\Http\Resources\Marketplace\ReviewResource;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Services\Marketplace\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    protected ReviewService $reviewService;

    public function __construct(ReviewService $reviewService)
    {
        $this->reviewService = $reviewService;
    }

    /**
     * Submit star rating & review for product or store
     */
    public function store(StoreReviewRequest $request): JsonResponse
    {
        try {
            $review = $this->reviewService->submitReview(
                $request->user(),
                $request->input('reviewable_type'),
                (int) $request->input('reviewable_id'),
                $request->validated(),
                $request
            );

            return response_success('Review submitted successfully.', new ReviewResource($review), 201);
        } catch (\Exception $e) {
            return response_error($e->getMessage(), [], 422);
        }
    }

    /**
     * Update an existing review
     */
    public function update(UpdateReviewRequest $request, Review $review): JsonResponse
    {
        try {
            $updatedReview = $this->reviewService->updateReview(
                $request->user(),
                $review,
                $request->validated(),
                $request
            );

            return response_success('Review updated successfully.', new ReviewResource($updatedReview));
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'Unauthorized') ? 403 : 422;
            return response_error($e->getMessage(), [], $statusCode);
        }
    }

    /**
     * Delete an existing review
     */
    public function destroy(Request $request, Review $review): JsonResponse
    {
        try {
            $this->reviewService->deleteReview($request->user(), $review);

            return response_success('Review deleted successfully and images removed.');
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'Unauthorized') ? 403 : 422;
            return response_error($e->getMessage(), [], $statusCode);
        }
    }

    /**
     * Get product reviews and 5-star distribution breakdown (cached via Redis tags)
     */
    public function productReviews(Request $request, Product $product): JsonResponse
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 15);
        $summary = $this->reviewService->getReviewsSummary($product, $page, $perPage);

        return response_success('Product reviews retrieved successfully.', $summary);
    }

    /**
     * Get store reviews and 5-star distribution breakdown (cached via Redis tags)
     */
    public function storeReviews(Request $request, Store $store): JsonResponse
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 15);
        $summary = $this->reviewService->getReviewsSummary($store, $page, $perPage);

        return response_success('Store reviews retrieved successfully.', $summary);
    }
}