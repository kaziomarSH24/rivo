<?php

namespace App\Services\Marketplace;

use App\Models\Pet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\User;

class MarketplaceHomeService
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Build the aggregated home feed for the marketplace
     */
    public function getHomeFeed(?User $user): array
    {
        // 1. Current Delivery Address
        $defaultAddress = null;
        if ($user) {
            $defaultAddress = $user->addresses()->where('is_default', true)->first()
                ?: $user->addresses()->latest()->first();
        }

        // 2. Active Pet for personalized recommendations
        $activePet = null;
        if ($user) {
            $requestedPetId = request()->query('pet_id');
            if ($requestedPetId) {
                $activePet = Pet::where('user_id', $user->id)->where('id', $requestedPetId)->first();
            }
            if (!$activePet) {
                $activePet = Pet::where('user_id', $user->id)->first();
            }
        }

        // 3. Categories with active product counts
        $categories = ProductCategory::query()
            ->where('is_active', true)
            ->withCount('products')
            ->orderBy('order_column')
            ->get();

        // 4. Popular Stores near user
        $popularStores = Store::where('is_live', true)
            ->where('is_verified', true)
            ->where('verification_status', 'verified')
            ->orderByDesc('rating')
            ->orderByDesc('reviews_count')
            ->limit(6)
            ->get();

        // 5. "Products for Pedro" (Personalized for active pet)
        $petProducts = $this->productService->getRecommendedForPet($activePet, 8);

        // 6. Top Deals / Discounted products
        $topDeals = Product::where('is_active', true)
            ->whereNotNull('discount_price')
            ->where('discount_price', '>', 0)
            ->where('stock', '>', 0)
            ->with(['brand', 'store'])
            ->limit(8)
            ->get();

        return [
            'delivery_address' => $defaultAddress ? [
                'id' => $defaultAddress->id,
                'title' => $defaultAddress->title,
                'street_address' => $defaultAddress->street_address,
                'city' => $defaultAddress->city,
                'is_default' => (bool) $defaultAddress->is_default,
            ] : null,
            'pro_banner' => [
                'title' => 'Rivo Pro',
                'subtitle' => 'Unlimited $0 Delivery Fee',
                'badge' => $user && method_exists($user, 'isPro') && $user->isPro() ? 'Active Member Benefit' : 'Upgrade to Pro',
            ],
            'categories' => $categories,
            'popular_stores' => $popularStores,
            'recommended_for_pet' => [
                'pet_name' => $activePet ? $activePet->name : 'Your Pet',
                'pet_type' => $activePet ? $activePet->type : 'dog',
                'products' => $petProducts,
            ],
            'top_deals' => $topDeals,
        ];
    }
}
