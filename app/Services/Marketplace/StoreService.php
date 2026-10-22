<?php

namespace App\Services\Marketplace;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\StoreDocument;
use App\Models\User;
use App\Http\Resources\Marketplace\ProductCardResource;
use App\Services\Marketplace\ProductService;
use App\Services\Marketplace\ReviewService;
use App\Services\BaseService;
use App\Traits\FileUploadTrait;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoreService extends BaseService
{
    use FileUploadTrait;

    protected string $modelClass = Store::class;

    protected function getAllowedIncludes(): array
    {
        return [
            AllowedInclude::relationship('products'),
            AllowedInclude::count('productsCount', 'products'),
            AllowedInclude::count('products_count', 'products'),
            AllowedInclude::exists('productsExists', 'products'),
            AllowedInclude::exists('products_exists', 'products'),

            AllowedInclude::relationship('documents'),
            AllowedInclude::count('documentsCount', 'documents'),
            AllowedInclude::count('documents_count', 'documents'),
            AllowedInclude::exists('documentsExists', 'documents'),
            AllowedInclude::exists('documents_exists', 'documents'),

            AllowedInclude::relationship('reviews'),
            AllowedInclude::count('reviewsCount', 'reviews'),
            AllowedInclude::count('reviews_count', 'reviews'),
            AllowedInclude::exists('reviewsExists', 'reviews'),
            AllowedInclude::exists('reviews_exists', 'reviews'),
        ];
    }

    protected function getAllowedFilters(): array
    {
        return [
            AllowedFilter::exact('type'),
            AllowedFilter::exact('is_verified'),
            AllowedFilter::exact('verification_status'),
            AllowedFilter::exact('is_live'),
            AllowedFilter::exact('city'),
            AllowedFilter::partial('name'),
        ];
    }

    protected function getAllowedSorts(): array
    {
        return ['created_at', 'rating', 'reviews_count', 'name', 'delivery_fee'];
    }

    /**
     * Register a new store / merchant
     */
    public function registerStore(User $user, array $data, Request $request): Store
    {
        // 1. Assign vendor role if not already assigned
        if (!$user->hasRole('vendor')) {
            $user->assignRole('vendor');
        }

        // 2. Handle logo and banner uploads
        $logoPath = $this->handleFileUpload($request, 'logo', 'stores/logos', 400, 400, 90, true);
        $bannerPath = $this->handleFileUpload($request, 'banner', 'stores/banners', 1200, 600, 90, true);

        // 3. Create or update the Store record
        $slug = Str::slug($data['name']);
        $uniqueSlug = $slug . '-' . Str::lower(Str::random(5));

        $store = Store::updateOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $data['name'],
                'slug' => $uniqueSlug,
                'type' => $data['type'] ?? 'pet_shop',
                'owner_name' => $data['owner_name'] ?? $user->name,
                'phone' => $data['phone'] ?? $user->phone_number,
                'email' => $data['email'] ?? $user->email,
                'street_address' => $data['street_address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'zip_code' => $data['zip_code'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'logo' => $logoPath,
                'banner' => $bannerPath,
                'rating' => 5.00,
                'reviews_count' => 0,
                'fulfillment_rate' => 100.00,
                'avg_prep_time_minutes' => 15,
                'delivery_fee' => $data['delivery_fee'] ?? 0.00,
                'delivery_time_text' => '15-20 min',
                'is_verified' => false,
                'verification_status' => 'pending',
                'is_live' => true,
            ]
        );

        // 4. Save uploaded KYC business documents if provided
        $this->saveStoreDocuments($store, $request);

        return $store->load(['documents']);
    }

    /**
     * Save uploaded business documents (KYC)
     */
    public function saveStoreDocuments(Store $store, Request $request): void
    {
        $docTypes = [
            'registration_certificate' => 'business_registration_certificate',
            'business_license' => 'business_license',
            'additional_document' => 'additional_document',
        ];

        foreach ($docTypes as $key => $type) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                $filePath = $file->store('stores/documents', 'public');

                StoreDocument::create([
                    'store_id' => $store->id,
                    'type' => $type,
                    'file_path' => $filePath,
                    'original_name' => $file->getClientOriginalName(),
                    'status' => 'pending',
                ]);
            }
        }
    }

    /**
     * Get vendor dashboard overview
     */
    public function getVendorDashboard(Store $store): array
    {
        $today = Carbon::today();

        // 1. Order status counts
        $orderCounts = [
            'all' => Order::where('store_id', $store->id)->count(),
            'new' => Order::where('store_id', $store->id)->where('status', 'new')->count(),
            'preparing' => Order::where('store_id', $store->id)->where('status', 'preparing')->count(),
            'ready' => Order::where('store_id', $store->id)->where('status', 'ready_for_pickup')->count(),
            'out_for_delivery' => Order::where('store_id', $store->id)->where('status', 'out_for_delivery')->count(),
        ];

        // 2. Today's sales
        $todaySales = (float) Order::where('store_id', $store->id)
            ->whereDate('created_at', $today)
            ->whereNotIn('status', ['cancelled'])
            ->sum('total');

        $yesterdaySales = (float) Order::where('store_id', $store->id)
            ->whereDate('created_at', Carbon::yesterday())
            ->whereNotIn('status', ['cancelled'])
            ->sum('total');

        $salesGrowth = $yesterdaySales > 0 ? round((($todaySales - $yesterdaySales) / $yesterdaySales) * 100, 1) : 0;

        // 3. Weekly sales chart data (Mon - Sun)
        $startOfWeek = Carbon::now()->startOfWeek();
        $weeklySales = [];
        for ($i = 0; $i < 7; $i++) {
            $day = (clone $startOfWeek)->addDays($i);
            $dayTotal = (float) Order::where('store_id', $store->id)
                ->whereDate('created_at', $day)
                ->whereNotIn('status', ['cancelled'])
                ->sum('total');

            $weeklySales[] = [
                'day' => $day->format('D'),
                'date' => $day->format('Y-m-d'),
                'total' => $dayTotal,
            ];
        }

        // 4. Low stock alerts
        $lowStockProducts = Product::where('store_id', $store->id)
            ->where('stock', '<=', 5)
            ->select(['id', 'name', 'stock', 'low_stock_threshold', 'images', 'price'])
            ->limit(5)
            ->get();

        // 5. Recent orders
        $recentOrders = Order::where('store_id', $store->id)
            ->with(['user:id,name,avatar', 'items'])
            ->latest()
            ->limit(5)
            ->get();

        return [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'logo' => $store->logo ? url('storage/' . $store->logo) : null,
                'is_live' => (bool) $store->is_live,
                'is_verified' => (bool) $store->is_verified,
                'rating' => (float) $store->rating,
                'fulfillment_rate' => (float) $store->fulfillment_rate,
                'avg_prep_time_minutes' => (int) $store->avg_prep_time_minutes,
            ],
            'order_overview' => $orderCounts,
            'today_sales' => [
                'amount' => $todaySales,
                'growth_percentage' => $salesGrowth,
                'weekly_chart' => $weeklySales,
            ],
            'low_stock_alerts' => $lowStockProducts,
            'recent_orders' => $recentOrders,
        ];
    }

    /**
     * Get vendor sales reports and analytics
     */
    public function getVendorAnalytics(Store $store, string $period = 'week'): array
    {
        $query = Order::where('store_id', $store->id);

        if ($period === 'today') {
            $query->whereDate('created_at', Carbon::today());
        } elseif ($period === 'month') {
            $query->where('created_at', '>=', Carbon::now()->subDays(30));
        } else {
            // default: week
            $query->where('created_at', '>=', Carbon::now()->startOfWeek());
        }

        $totalSales = (float) (clone $query)->whereNotIn('status', ['cancelled'])->sum('total');
        $totalOrders = (clone $query)->count();
        $completedOrders = (clone $query)->where('status', 'delivered')->count();
        $cancelledOrders = (clone $query)->where('status', 'cancelled')->count();
        $completionRate = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 1) : 100.0;
        $avgOrderValue = $completedOrders > 0 ? round($totalSales / $completedOrders, 2) : 0.0;

        // Best sellers list
        $bestSellers = Product::where('store_id', $store->id)
            ->orderByDesc('sales_count')
            ->limit(5)
            ->get(['id', 'name', 'price', 'sales_count', 'images']);

        return [
            'period' => $period,
            'total_sales' => $totalSales,
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'cancelled_orders' => $cancelledOrders,
            'completion_rate' => $completionRate,
            'avg_order_value' => $avgOrderValue,
            'best_sellers' => $bestSellers,
        ];
    }

    /**
     * Get store public profile data including categories, review breakdown, and products
     */
    public function getStoreProfile(Store $store, Request $request): array
    {
        // 1. Categories available in this specific store (for "Browse Categories" carousel)
        $categoryIds = Product::where('store_id', $store->id)
            ->where('is_active', true)
            ->distinct()
            ->pluck('category_id');

        $categories = ProductCategory::whereIn('id', $categoryIds)
            ->where('is_active', true)
            ->orderBy('order_column')
            ->get();

        // 2. Reviews summary & 5-star breakdown via polymorphic relation
        $reviewsSummary = app(ReviewService::class)->getReviewsSummary($store);

        // 3. Products in this store with filtering (e.g. ?category_id=...) & sorting
        $products = app(ProductService::class)->getAll(function ($q) use ($store, $request) {
            $q->where('store_id', $store->id)->where('is_active', true);
            if ($request->filled('category_id')) {
                $q->where('category_id', $request->input('category_id'));
            }
        });

        $paginatedProducts = ProductCardResource::collection($products)->response()->getData(true);

        return [
            'store' => $store,
            'categories' => $categories,
            'reviews_summary' => $reviewsSummary,
            'products' => $paginatedProducts,
        ];
    }

}
