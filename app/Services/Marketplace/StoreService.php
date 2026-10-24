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
use Illuminate\Support\Facades\Cache;

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
     * Register a new store / merchant (or safely update if already exists)
     */
    public function registerStore(User $user, array $data, Request $request): Store
    {
        // 1. Assign vendor role if not already assigned
        if (!$user->hasRole('vendor')) {
            $user->assignRole('vendor');
        }

        $existingStore = Store::where('user_id', $user->id)->first();

        // 2. Handle logo and banner uploads (with WebP conversion and old image cleanup)
        $logoPath = null;
        if ($request->hasFile('logo')) {
            if ($existingStore && $existingStore->logo) {
                $this->deleteFile($existingStore->logo);
            }
            $logoPath = $this->handleFileUpload($request, 'logo', 'stores/logos', 400, 400, 90, true);
        } elseif ($existingStore) {
            $logoPath = $existingStore->logo;
        }

        $bannerPath = null;
        if ($request->hasFile('banner')) {
            if ($existingStore && $existingStore->banner) {
                $this->deleteFile($existingStore->banner);
            }
            $bannerPath = $this->handleFileUpload($request, 'banner', 'stores/banners', 1200, 600, 90, true);
        } elseif ($existingStore) {
            $bannerPath = $existingStore->banner;
        }

        // 3. Determine slug: preserve existing slug if updating
        if ($existingStore && !empty($existingStore->slug)) {
            $slug = $existingStore->slug;
        } else {
            $baseSlug = Str::slug($data['name']);
            $slug = $baseSlug . '-' . Str::lower(Str::random(5));
        }

        $store = Store::updateOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $data['name'],
                'slug' => $slug,
                'type' => $data['type'] ?? ($existingStore?->type ?? 'pet_shop'),
                'owner_name' => $data['owner_name'] ?? ($existingStore?->owner_name ?? $user->name),
                'phone' => $data['phone'] ?? ($existingStore?->phone ?? $user->phone_number),
                'email' => $data['email'] ?? ($existingStore?->email ?? $user->email),
                'street_address' => $data['street_address'] ?? ($existingStore?->street_address ?? null),
                'city' => $data['city'] ?? ($existingStore?->city ?? null),
                'state' => $data['state'] ?? ($existingStore?->state ?? null),
                'zip_code' => $data['zip_code'] ?? ($existingStore?->zip_code ?? null),
                'latitude' => $data['latitude'] ?? ($existingStore?->latitude ?? null),
                'longitude' => $data['longitude'] ?? ($existingStore?->longitude ?? null),
                'logo' => $logoPath,
                'banner' => $bannerPath,
                'rating' => $existingStore?->rating ?? 5.00,
                'reviews_count' => $existingStore?->reviews_count ?? 0,
                'fulfillment_rate' => $existingStore?->fulfillment_rate ?? 100.00,
                'avg_prep_time_minutes' => $existingStore?->avg_prep_time_minutes ?? 15,
                'delivery_fee' => $data['delivery_fee'] ?? ($existingStore?->delivery_fee ?? 0.00),
                'delivery_time_text' => $data['delivery_time_text'] ?? ($existingStore?->delivery_time_text ?? '15-20 min'),
                'is_verified' => $existingStore?->is_verified ?? false,
                'verification_status' => $existingStore?->verification_status ?? 'pending',
                'is_live' => $existingStore?->is_live ?? true,
            ]
        );

        // 4. Save uploaded KYC business documents if provided
        $this->saveStoreDocuments($store, $request);

        // 5. Invalidate caches
        $this->flushCacheTags(['stores', "stores:{$store->id}"]);

        return $store->load(['documents']);
    }

    /**
     * Update vendor store profile details, images, and settings
     */
    public function updateStoreProfile(Store $store, array $data, Request $request): Store
    {
        // 1. Handle logo upload (WebP) and delete old file
        if ($request->hasFile('logo')) {
            if ($store->logo) {
                $this->deleteFile($store->logo);
            }
            $store->logo = $this->handleFileUpload($request, 'logo', 'stores/logos', 400, 400, 90, true);
        }

        // 2. Handle banner upload (WebP) and delete old file
        if ($request->hasFile('banner')) {
            if ($store->banner) {
                $this->deleteFile($store->banner);
            }
            $store->banner = $this->handleFileUpload($request, 'banner', 'stores/banners', 1200, 600, 90, true);
        }

        // 3. Update fillable fields if present
        $fieldsToUpdate = [
            'name', 'type', 'owner_name', 'phone', 'email',
            'street_address', 'city', 'state', 'zip_code',
            'latitude', 'longitude', 'delivery_fee',
            'delivery_time_text', 'is_live'
        ];

        foreach ($fieldsToUpdate as $field) {
            if (array_key_exists($field, $data)) {
                $store->{$field} = $data[$field];
            }
        }

        $store->save();

        // 4. Invalidate store cache
        $this->flushCacheTags(['stores', "stores:{$store->id}"]);

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
     * Upload an individual KYC business document
     */
    public function uploadDocument(Store $store, array $data, Request $request): StoreDocument
    {
        $file = $request->file('document');
        $filePath = $file->store('stores/documents', 'public');

        $doc = StoreDocument::create([
            'store_id' => $store->id,
            'type' => $data['type'],
            'file_path' => $filePath,
            'original_name' => $file->getClientOriginalName(),
            'status' => 'pending',
        ]);

        $this->flushCacheTags(['stores', "stores:{$store->id}"]);

        return $doc;
    }

    /**
     * Delete a KYC document from database and storage disk
     */
    public function deleteDocument(Store $store, StoreDocument $document): bool
    {
        if ($document->file_path) {
            $this->deleteFile($document->file_path);
        }

        $deleted = $document->delete();

        $this->flushCacheTags(['stores', "stores:{$store->id}"]);

        return (bool) $deleted;
    }
        /**
     * Get vendor dashboard overview (cached via Redis tags)
     */
    public function getVendorDashboard(Store $store): array
    {
        $cacheTags = ["stores:{$store->id}:dashboard", "stores:{$store->id}", 'stores'];
        $cacheKey = "vendor:dashboard:{$store->id}:" . Carbon::today()->toDateString();

        return $this->rememberCache($cacheKey, function () use ($store) {
            $today = Carbon::today();

            // 1. Order status counts (including 'done' / delivered matching Figma overview)
            $orderCounts = [
                'all' => Order::where('store_id', $store->id)->count(),
                'new' => Order::where('store_id', $store->id)->where('status', 'new')->count(),
                'preparing' => Order::where('store_id', $store->id)->where('status', 'preparing')->count(),
                'ready' => Order::where('store_id', $store->id)->where('status', 'ready_for_pickup')->count(),
                'done' => Order::where('store_id', $store->id)->where('status', 'delivered')->count(),
                'out_for_delivery' => Order::where('store_id', $store->id)->where('status', 'out_for_delivery')->count(),
            ];

            // 2. Today's sales & growth percentage vs yesterday
            $todaySales = (float) Order::where('store_id', $store->id)
                ->whereDate('created_at', $today)
                ->whereNotIn('status', ['cancelled'])
                ->sum('total');

            $yesterdaySales = (float) Order::where('store_id', $store->id)
                ->whereDate('created_at', Carbon::yesterday())
                ->whereNotIn('status', ['cancelled'])
                ->sum('total');

            $salesGrowth = $yesterdaySales > 0 ? round((($todaySales - $yesterdaySales) / $yesterdaySales) * 100, 1) : 0.0;

            // 3. Weekly sales chart data (Mon - Sun, with is_today indicator)
            $startOfWeek = Carbon::now()->startOfWeek();
            $weeklySales = [];
            $todayDate = $today->toDateString();
            for ($i = 0; $i < 7; $i++) {
                $day = (clone $startOfWeek)->addDays($i);
                $dayDate = $day->toDateString();
                $dayTotal = (float) Order::where('store_id', $store->id)
                    ->whereDate('created_at', $day)
                    ->whereNotIn('status', ['cancelled'])
                    ->sum('total');

                $weeklySales[] = [
                    'day' => $day->format('D'),
                    'date' => $dayDate,
                    'total' => $dayTotal,
                    'is_today' => ($dayDate === $todayDate),
                ];
            }

            // 4. Low stock alerts (<5 units) formatted for Figma Inventory Alert cards
            $lowStockProducts = Product::where('store_id', $store->id)
                ->where('stock', '<=', 5)
                ->select(['id', 'name', 'stock', 'low_stock_threshold', 'images', 'price'])
                ->limit(5)
                ->get()
                ->map(function ($product) {
                    $firstImage = null;
                    if (!empty($product->images)) {
                        $imgArray = is_array($product->images) ? $product->images : json_decode($product->images, true);
                        $firstImage = is_array($imgArray) ? ($imgArray[0] ?? null) : null;
                    }
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'stock' => (int) $product->stock,
                        'badge' => $product->stock <= 0 ? 'Out of Stock' : "{$product->stock} left",
                        'price' => (float) $product->price,
                        'image' => format_media_url($firstImage, 'products'),
                    ];
                });

            // 5. Recent orders formatted for Figma Recent Orders cards
            $recentOrders = Order::where('store_id', $store->id)
                ->with(['user:id,name,avatar', 'items'])
                ->latest()
                ->limit(5)
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'customer_name' => $order->user?->name ?? 'Guest Customer',
                        'customer_avatar' => format_media_url($order->user?->avatar, 'avatars'),
                        'time_ago' => $order->created_at?->diffForHumans() ?? 'Just now',
                        'total' => (float) $order->total,
                        'status' => $order->status,
                        'status_label' => ucfirst(str_replace('_', ' ', $order->status)),
                        'items_count' => $order->items ? $order->items->count() : 0,
                    ];
                });

            return [
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'logo' => format_media_url($store->logo, 'stores/logos'),
                    'is_live' => (bool) $store->is_live,
                    'is_verified' => (bool) $store->is_verified,
                    'rating' => (float) ($store->rating ?? 5.0),
                    'reviews_count' => (int) ($store->reviews_count ?? 0),
                    'fulfillment_rate' => (float) ($store->fulfillment_rate ?? 100.0),
                    'avg_prep_time_minutes' => (int) ($store->avg_prep_time_minutes ?? 15),
                ],
                'order_overview' => $orderCounts,
                'today_sales' => [
                    'amount' => $todaySales,
                    'growth_percentage' => $salesGrowth,
                    'weekly_chart' => $weeklySales,
                ],
                'store_performance' => [
                    'rating' => (float) ($store->rating ?? 5.0),
                    'reviews_count' => (int) ($store->reviews_count ?? 0),
                    'success_rate' => ((float) ($store->fulfillment_rate ?? 100.0)) . '%',
                    'avg_time' => '< ' . ((int) ($store->avg_prep_time_minutes ?? 15)) . 'm',
                ],
                'low_stock_alerts' => $lowStockProducts,
                'recent_orders' => $recentOrders,
            ];
        }, 300, $cacheTags, lock: true);
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
