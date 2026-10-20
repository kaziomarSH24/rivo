<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreVendorProductRequest;
use App\Http\Requests\Vendor\UpdateProductStockRequest;
use App\Http\Requests\Vendor\UpdateVendorProductRequest;
use App\Models\Product;
use App\Services\Marketplace\ProductService;
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
     * List store products with Spatie QueryBuilder filtering & pagination via BaseService
     */
    public function index(Request $request): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store) {
            return response_error('No store registered for this account.', [], 404);
        }

        $products = $this->productService->getAll(function ($query) use ($store) {
            $query->where('store_id', $store->id);
        });

        return response_success('Catalog retrieved successfully.', $products);
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