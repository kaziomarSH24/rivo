<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\StoreReviewRequest;
use App\Http\Resources\Marketplace\ReviewResource;
use App\Models\Product;
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
     * Get product reviews and 5-star distribution breakdown
     */
    public function productReviews(Request $request, Product $product): JsonResponse
    {
        $summary = $this->reviewService->getReviewsSummary($product);

        return response_success('Product reviews retrieved successfully.', $summary);
    }

    /**
     * Get store reviews and 5-star distribution breakdown
     */
    public function storeReviews(Request $request, Store $store): JsonResponse
    {
        $summary = $this->reviewService->getReviewsSummary($store);

        return response_success('Store reviews retrieved successfully.', $summary);
    }
}