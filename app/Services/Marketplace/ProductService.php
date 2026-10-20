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
     * Create product with 4-step wizard data
     */
    public function storeProduct(Store $store, array $data, Request $request): Product
    {
        $slug = Str::slug($data['name']) . '-' . Str::lower(Str::random(5));

        // 1. Handle product images
        $uploadedImages = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $filename = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
                $uploadedImages[] = $file->storeAs('products', $filename, 'public');
            }
        }

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
            'images' => $uploadedImages,
        ];

        /** @var Product $product */
        $product = $this->create($productData);

        // 2. Handle variants if provided
        if (!empty($data['variants']) && is_array($data['variants'])) {
            foreach ($data['variants'] as $variantData) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'flavor' => $variantData['flavor'] ?? null,
                    'size_lbs' => $variantData['size_lbs'] ?? null,
                    'price' => $variantData['price'] ?? $product->price,
                    'stock' => $variantData['stock'] ?? $product->stock,
                    'sku' => $variantData['sku'] ?? ($product->sku . '-' . strtoupper(Str::random(3))),
                ]);
            }
        }

        return $product->load(['category', 'brand', 'variants']);
    }

    /**
     * Update product
     */
    public function updateProduct(Product $product, array $data, Request $request): Product
    {
        $uploadedImages = $product->images ?: [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $filename = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
                $uploadedImages[] = $file->storeAs('products', $filename, 'public');
            }
            $data['images'] = $uploadedImages;
        }

        return $this->update($product->id, $data)->load(['category', 'brand', 'variants']);
    }

    /**
     * Quick stock stepper adjustment
     */
    public function updateStock(Product $product, int $newStock): Product
    {
        $product->stock = max(0, $newStock);
        $product->save();

        return $product;
    }

    /**
     * Toggle product visibility
     */
    public function toggleActive(Product $product): Product
    {
        $product->is_active = !$product->is_active;
        $product->save();

        return $product;
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