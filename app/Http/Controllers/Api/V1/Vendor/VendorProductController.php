<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreVendorProductRequest;
use App\Http\Requests\Vendor\UpdateProductStockRequest;
use App\Http\Requests\Vendor\UpdateVendorProductRequest;
use App\Models\Product;
use App\Services\Marketplace\ProductService;
use App\Http\Resources\Vendor\VendorProductCardResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * List store products with tab counters, status filters, search & resource formatting matching Figma
     */
    public function index(Request $request): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store) {
            return response_error('No store registered for this account.', [], 404);
        }

        // 1. Compute live tab counters matching Figma UI pills (All, Active, Draft, Out of Stock)
        $baseQuery = Product::where('store_id', $store->id);
        $counters = [
            'all' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('is_active', true)->where('stock', '>', 0)->count(),
            'draft' => (clone $baseQuery)->where('is_active', false)->count(),
            'out_of_stock' => (clone $baseQuery)->where('stock', '<=', 0)->count(),
        ];

        // 2. Extract filter inputs (supports both ?status=active & Spatie ?filter[status]=active)
        $status = $request->input('status', $request->input('filter.status'));
        $search = $request->input('search', $request->input('filter.search'));

        // 3. Fetch paginated products with eager-loaded relations
        $products = $this->productService->getAll(function ($query) use ($store, $status, $search) {
            $query->where('store_id', $store->id)
                  ->with(['category:id,name', 'brand:id,name']);

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
        });

        // 4. Transform collection with VendorProductCardResource
        $transformedData = VendorProductCardResource::collection($products->getCollection())->resolve();

        return response_success('Catalog retrieved successfully.', [
            'counters' => $counters,
            'current_page' => $products->currentPage(),
            'data' => $transformedData,
            'first_page_url' => $products->url(1),
            'from' => $products->firstItem(),
            'last_page' => $products->lastPage(),
            'last_page_url' => $products->url($products->lastPage()),
            'next_page_url' => $products->nextPageUrl(),
            'path' => $products->path(),
            'per_page' => $products->perPage(),
            'prev_page_url' => $products->previousPageUrl(),
            'to' => $products->lastItem(),
            'total' => $products->total(),
        ]);
    }

    /**
     * Create product via 4-step wizard
     */
    public function store(StoreVendorProductRequest $request): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store) {
            return response_error('No store registered for this account.', [], 404);
        }

        $product = $this->productService->storeProduct($store, $request->validated(), $request);

        return response_success('Product published successfully.', $product, 201);
    }

    /**
     * Show product details
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store || $product->store_id !== $store->id) {
            return response_error('Unauthorized access to product.', [], 403);
        }

        return response_success('Product details retrieved.', $product->load(['category', 'brand', 'variants']));
    }

    /**
     * Update product details
     */
    public function update(UpdateVendorProductRequest $request, Product $product): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store || $product->store_id !== $store->id) {
            return response_error('Unauthorized access to product.', [], 403);
        }

        $updatedProduct = $this->productService->updateProduct($product, $request->validated(), $request);

        return response_success('Product updated successfully.', $updatedProduct);
    }

    /**
     * Quick inventory stock adjustment
     */
    public function updateStock(UpdateProductStockRequest $request, Product $product): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store || $product->store_id !== $store->id) {
            return response_error('Unauthorized access to product.', [], 403);
        }

        $updated = $this->productService->updateStock($product, (int) $request->input('stock'));

        return response_success('Stock updated successfully.', [
            'id' => $updated->id,
            'name' => $updated->name,
            'stock' => $updated->stock,
        ]);
    }

    /**
     * Toggle active listing status
     */
    public function toggleActive(Request $request, Product $product): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store || $product->store_id !== $store->id) {
            return response_error('Unauthorized access to product.', [], 403);
        }

        $updated = $this->productService->toggleActive($product);

        return response_success('Listing status updated.', [
            'id' => $updated->id,
            'is_active' => $updated->is_active,
        ]);
    }

    /**
     * Soft delete product
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store || $product->store_id !== $store->id) {
            return response_error('Unauthorized access to product.', [], 403);
        }

        $product->delete();

        return response_success('Product deleted successfully.', []);
    }
}