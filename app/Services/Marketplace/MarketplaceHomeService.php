<?php

namespace App\Services\Marketplace;

use App\Models\Pet;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Marketplace\StoreService;
use App\Models\ProductCategory;
use App\Models\RecentSearch;
use Illuminate\Support\Facades\DB;

class MarketplaceHomeService
{
    protected ProductService $productService;
    protected CategoryService $categoryService;
    protected StoreService $storeService;

    public function __construct(
        ProductService $productService,
        CategoryService $categoryService,
        StoreService $storeService
    ) {
        $this->productService = $productService;
        $this->categoryService = $categoryService;
        $this->storeService = $storeService;
    }

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

        // 3. Categories with active product counts (Delegated to CategoryService)
        $categories = $this->categoryService->getActiveCategories();

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

        // 6. Top Deals / Discounted products (Latest discounted deals)
        $topDeals = Product::where('is_active', true)
            ->whereNotNull('discount_price')
            ->where('discount_price', '>', 0)
            ->where('stock', '>', 0)
            ->latest('updated_at')
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

    /**
     * Unified search across products, stores, and recent searches
     */
    public function search(?string $query, ?User $user): array
    {
        // 1. Record recent search if query is provided and user is authenticated
        if ($query && $user) {
            RecentSearch::updateOrCreate(
                ['user_id' => $user->id, 'query' => trim($query)],
                ['updated_at' => now()]
            );
        }

        // 2. Recent searches for user
        $recentSearches = $user
            ? RecentSearch::where('user_id', $user->id)->latest('updated_at')->limit(5)->pluck('query')->toArray()
            : [];

        // 3. Popular search tags dynamically from recent searches or active categories
        $popularTags = RecentSearch::select('query', DB::raw('count(*) as search_count'))
            ->groupBy('query')
            ->orderByDesc('search_count')
            ->limit(8)
            ->pluck('query')
            ->toArray();

        if (empty($popularTags)) {
            $popularTags = ProductCategory::where('is_active', true)
                ->orderBy('order_column')
                ->limit(6)
                ->pluck('name')
                ->toArray();
        }

        // 4. Search products & stores via BaseService
        $products = [];
        $stores = [];

        if ($query) {
            $products = $this->productService->getAll(function ($q) use ($query) {
                $q->where('is_active', true)
                    ->where(function ($sub) use ($query) {
                        $sub->where('name', 'like', "%{$query}%")
                            ->orWhere('description', 'like', "%{$query}%");
                    });
            });

            $stores = $this->storeService->getAll(function ($q) use ($query) {
                $q->where('is_live', true)
                    ->where(function ($sub) use ($query) {
                        $sub->where('name', 'like', "%{$query}%")
                            ->orWhere('city', 'like', "%{$query}%");
                    });
            });
        }

        return [
            'recent_searches' => $recentSearches,
            'popular_tags' => $popularTags,
            'products' => $products,
            'stores' => $stores,
        ];
    }

    /**
     * Clear all recent searches for a user
     */
    public function clearRecentSearches(?User $user): void
    {
        if ($user) {
            RecentSearch::where('user_id', $user->id)->delete();
        }
    }
}
