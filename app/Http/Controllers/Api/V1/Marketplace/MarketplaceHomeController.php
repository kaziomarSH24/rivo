<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RecentSearch;
use App\Models\Store;
use App\Services\Marketplace\ProductService;
use App\Services\Marketplace\ReviewService;
use App\Services\Marketplace\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketplaceHomeController extends Controller
{
    protected ProductService $productService;
    protected StoreService $storeService;
    protected ReviewService $reviewService;

    public function __construct(
        ProductService $productService,
        StoreService $storeService,
        ReviewService $reviewService
    ) {
        $this->productService = $productService;
        $this->storeService = $storeService;
        $this->reviewService = $reviewService;
    }

    /**
     * Marketplace Home Feed matching Figma Discovery screen
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Current Delivery Address
        $defaultAddress = $user ? $user->addresses()->where('is_default', true)->first() : null;
        if (!$defaultAddress && $user) {
            $defaultAddress = $user->addresses()->latest()->first();
        }

        // 2. Active Pet for personalized recommendations (e.g. Pedro)
        $activePet = null;
        if ($user) {
            $activePet = Pet::where('user_id', $user->id)->first();
        }

        // 3. Categories with product counts
        $categories = ProductCategory::query()
            ->where('is_active', true)
            ->withCount('products')
            ->orderBy('order_column')
            ->get();

        // 4. Popular Stores near user
        $popularStores = Store::where('is_live', true)
            ->where('is_verified', true)
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

        return response_success('Marketplace home feed retrieved successfully.', [
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
                'badge' => 'Active Member Benefit',
            ],
            'categories' => $categories,
            'popular_stores' => $popularStores,
            'recommended_for_pet' => [
                'pet_name' => $activePet ? $activePet->name : 'Your Pet',
                'pet_type' => $activePet ? $activePet->type : 'dog',
                'products' => $petProducts,
            ],
            'top_deals' => $topDeals,
        ]);
    }

    /**
     * Browse all stores with filters (type, city, rating, sort)
     */
    public function stores(Request $request): JsonResponse
    {
        $stores = $this->storeService->getAll(function ($q) {
            $q->where('is_live', true);
        });

        return response_success('Stores list retrieved successfully.', $stores);
    }

    /**
     * Browse all marketplace products with Spatie QueryBuilder filters & sorting
     */
    public function products(Request $request): JsonResponse
    {
        $products = $this->productService->getAll(function ($q) {
            $q->where('is_active', true);
        });

        return response_success('Products catalog retrieved successfully.', $products);
    }

    /**
     * Products by specific category
     */
    public function categoryProducts(Request $request, ProductCategory $category): JsonResponse
    {
        $products = $this->productService->getAll(function ($q) use ($category) {
            $q->where('category_id', $category->id)->where('is_active', true);
        });

        return response_success("Products for {$category->name} retrieved successfully.", $products);
    }

    /**
     * Categories list with subcategories
     */
    public function categories(): JsonResponse
    {
        $categories = ProductCategory::query()
            ->where('is_active', true)
            ->with(['children' => function ($q) {
                $q->where('is_active', true)->withCount('products');
            }])
            ->withCount('products')
            ->orderBy('order_column')
            ->get();

        return response_success('Product categories retrieved successfully.', $categories);
    }

    /**
     * Unified Search with 2 tabs: Products & Stores + Recent Searches
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->query('q');
        $user = $request->user();

        // Record recent search if query is provided and user is authenticated
        if ($query && $user) {
            RecentSearch::updateOrCreate(
                ['user_id' => $user->id, 'query' => trim($query)],
                ['updated_at' => now()]
            );
        }

        // Recent searches for user
        $recentSearches = $user
            ? RecentSearch::where('user_id', $user->id)->latest('updated_at')->limit(5)->pluck('query')
            : [];

        // Popular search tags dynamically from recent searches or active categories
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

        // Search products via BaseService
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

        return response_success('Search results retrieved.', [
            'recent_searches' => $recentSearches,
            'popular_tags' => $popularTags,
            'products' => $products,
            'stores' => $stores,
        ]);
    }

    /**
     * Clear user recent searches
     */
    public function clearRecentSearches(Request $request): JsonResponse
    {
        if ($request->user()) {
            RecentSearch::where('user_id', $request->user()->id)->delete();
        }

        return response_success('Recent searches cleared successfully.', []);
    }

    /**
     * Store profile & catalog
     */
    public function storeProfile(Request $request, Store $store): JsonResponse
    {
        $store->load(['documents']);
        $reviewSummary = $this->reviewService->getReviewsSummary($store);

        $products = $this->productService->getAll(function ($q) use ($store) {
            $q->where('store_id', $store->id)->where('is_active', true);
        });

        return response_success('Store details retrieved successfully.', [
            'store' => $store,
            'reviews_summary' => $reviewSummary,
            'products' => $products,
        ]);
    }

    /**
     * Product details with variants & reviews breakdown
     */
    public function productDetails(Request $request, Product $product): JsonResponse
    {
        $product->load(['store:id,name,logo,rating,delivery_time_text,delivery_fee', 'category', 'brand', 'variants']);
        $reviewsSummary = $this->reviewService->getReviewsSummary($product);

        return response_success('Product details retrieved successfully.', [
            'product' => $product,
            'reviews_summary' => $reviewsSummary,
        ]);
    }
}