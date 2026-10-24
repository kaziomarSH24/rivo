<?php

namespace App\Services\Marketplace;

use App\Models\Pet;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\BaseService;
use App\Traits\FileUploadTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;

class ProductService extends BaseService
{
    use FileUploadTrait;

    protected string $modelClass = Product::class;

    protected function getAllowedIncludes(): array
    {
        return ['store', 'category', 'brand', 'variants', 'reviews'];
    }

    protected function getAllowedFilters(): array
    {
        return [
            AllowedFilter::exact('store_id'),
            AllowedFilter::exact('category_id'),
            AllowedFilter::exact('brand_id'),
            AllowedFilter::exact('pet_type'),
            AllowedFilter::exact('is_active'),
            AllowedFilter::scope('active'),
            AllowedFilter::scope('out_of_stock', 'outOfStock'),
            AllowedFilter::scope('inactive'),
            AllowedFilter::partial('search', 'name'),
            AllowedFilter::partial('name'),
        ];
    }

    protected function getAllowedSorts(): array
    {
        return ['created_at', 'price', 'rating', 'sales_count', 'name', 'stock'];
    }

    /**
     * Get vendor products catalog with live tab counters, status filters, search and caching
     */
    public function getVendorCatalog(Store $store, Request $request): array
    {
        $status = $request->input('status', $request->input('filter.status', 'all'));
        $search = $request->input('search', $request->input('filter.search', ''));
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 15);
        $sort = $request->input('sort', '-created_at');

        $cacheTags = ["stores:{$store->id}:products", "stores:{$store->id}", 'stores'];
        $cacheKey = "vendor:catalog:{$store->id}:s_{$status}:q_" . md5($search) . ":p_{$page}:pp_{$perPage}:sort_{$sort}";

        return $this->rememberCache($cacheKey, function () use ($store, $status, $search, $perPage) {
            // 1. Compute live tab counters matching Figma UI pills (All, Active, Draft, Out of Stock)
            $baseQuery = Product::where('store_id', $store->id);
            $counters = [
                'all' => (clone $baseQuery)->count(),
                'active' => (clone $baseQuery)->where('is_active', true)->where('stock', '>', 0)->count(),
                'draft' => (clone $baseQuery)->where('is_active', false)->count(),
                'out_of_stock' => (clone $baseQuery)->where('stock', '<=', 0)->count(),
            ];

            // 2. Fetch filtered products with eager loaded relations
            $query = Product::where('store_id', $store->id)
                ->with(['category:id,name', 'brand:id,name', 'variants']);

            if ($status === 'active') {
                $query->where('is_active', true)->where('stock', '>', 0);
            } elseif ($status === 'draft' || $status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($status === 'out_of_stock') {
                $query->where('stock', '<=', 0);
            }

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            $paginator = $query->latest('id')->paginate($perPage);

            return [
                'counters' => $counters,
                'paginator' => $paginator,
            ];
        }, ttl: 300, tags: $cacheTags, lock: true);
    }

    /**
     * Create product with 4-step wizard data.
     * Supports both uploaded binary images (multipart/form-data) and image URLs/strings (application/json).
     * Cache invalidation is handled automatically by the Product model's AutoClearsCache trait.
     */
    public function storeProduct(Store $store, array $data, Request $request): Product
    {
        $slug = Str::slug($data['name']) . '-' . Str::lower(Str::random(5));

        // 1. Handle uploaded image files via FileUploadTrait (converts to optimized WebP)
        $uploadedImages = [];
        if ($request->hasFile('images')) {
            $uploadedImages = $this->handleMultipleFileUpload($request, 'images', 'products', forceWebp: true);
        }

        // 2. Collect any existing string URLs passed via JSON body or form fields
        $urlImages = [];
        if (!empty($data['images']) && is_array($data['images'])) {
            $urlImages = array_values(array_filter($data['images'], fn($img) => is_string($img) && trim($img) !== ''));
        }

        $allImages = array_values(array_unique(array_merge($urlImages, $uploadedImages)));

        $productData = [
            'store_id' => $store->id,
            'category_id' => $data['category_id'],
            'brand_id' => $data['brand_id'] ?? null,
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'pet_type' => $data['pet_type'] ?? 'both',
            'price' => $data['price'],
            'discount_price' => $data['discount_price'] ?? null,
            'sku' => $data['sku'] ?? ('SKU-' . strtoupper(Str::random(8))),
            'stock' => $data['stock'] ?? 0,
            'low_stock_threshold' => $data['low_stock_threshold'] ?? 5,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'is_online' => isset($data['is_online']) ? (bool) $data['is_online'] : true,
            'is_instore' => isset($data['is_instore']) ? (bool) $data['is_instore'] : true,
            'images' => $allImages,
        ];

        /** @var Product $product */
        $product = $this->create($productData);

        // 3. Handle variants if provided
        if (!empty($data['variants']) && is_array($data['variants'])) {
            foreach ($data['variants'] as $variantData) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'flavor' => $variantData['flavor'] ?? null,
                    'size_lbs' => $variantData['size_lbs'] ?? null,
                    'price' => $variantData['price'] ?? $product->price,
                    'stock' => $variantData['stock'] ?? $product->stock,
                ]);
            }
        }

        return $product->load(['category', 'brand', 'variants']);
    }

    /**
     * Update product details, image assets, and variants.
     * Cache invalidation is handled automatically by the Product model's AutoClearsCache trait.
     */
    public function updateProduct(Product $product, array $data, Request $request): Product
    {
        $currentImages = (array) ($product->images ?: []);

        // 1. Upload any newly provided image files
        $newUploadedImages = [];
        if ($request->hasFile('images')) {
            $newUploadedImages = $this->handleMultipleFileUpload($request, 'images', 'products', forceWebp: true);
        }

        // 2. Merge or replace images
        if (array_key_exists('images', $data) && is_array($data['images'])) {
            $providedUrls = array_values(array_filter($data['images'], fn($img) => is_string($img) && trim($img) !== ''));
            $data['images'] = array_values(array_unique(array_merge($providedUrls, $newUploadedImages)));
        } elseif (!empty($newUploadedImages)) {
            $data['images'] = array_values(array_unique(array_merge($currentImages, $newUploadedImages)));
        }

        // 3. Update main product attributes
        /** @var Product $updatedProduct */
        $updatedProduct = $this->update($product->id, $data);

        // 4. Handle variants synchronization if provided
        if (array_key_exists('variants', $data)) {
            $updatedProduct->variants()->delete();
            if (is_array($data['variants'])) {
                foreach ($data['variants'] as $variantData) {
                    ProductVariant::create([
                        'product_id' => $updatedProduct->id,
                        'flavor' => $variantData['flavor'] ?? null,
                        'size_lbs' => $variantData['size_lbs'] ?? null,
                        'price' => $variantData['price'] ?? $updatedProduct->price,
                        'stock' => $variantData['stock'] ?? $updatedProduct->stock,
                    ]);
                }
            }
        }

        return $updatedProduct->load(['category', 'brand', 'variants']);
    }

    /**
     * Quick stock stepper adjustment (Cache automatically cleared via model events)
     */
    public function updateStock(Product $product, int $newStock): Product
    {
        $product->stock = max(0, $newStock);
        $product->save();

        return $product;
    }

    /**
     * Toggle product visibility (Cache automatically cleared via model events)
     */
    public function toggleActive(Product $product): Product
    {
        $product->is_active = !$product->is_active;
        $product->save();

        return $product;
    }

    /**
     * Delete product safely (Cache automatically cleared via model events)
     */
    public function deleteProduct(Product $product): bool
    {
        return (bool) $product->delete();
    }

    /**
     * Get personalized products for user's active pet (e.g., Pedro)
     */
    public function getRecommendedForPet(?Pet $pet, int $limit = 8)
    {
        $query = Product::where('is_active', true)->where('stock', '>', 0)->with(['brand', 'store']);

        if ($pet && $pet->type) {
            $petType = strtolower($pet->type);
            $query->where(function ($q) use ($petType) {
                $q->where('pet_type', $petType)->orWhere('pet_type', 'both');
            });
        }

        return $query->inRandomOrder()->limit($limit)->get();
    }
}
