<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreVendorProductRequest;
use App\Http\Requests\Vendor\UpdateProductStockRequest;
use App\Http\Requests\Vendor\UpdateVendorProductRequest;
use App\Models\Product;
use App\Services\Marketplace\ProductService;
use App\Http\Resources\Vendor\VendorProductCardResource;
use App\Http\Resources\Vendor\VendorProductCatalogResource;
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
        $store = $request->attributes->get('store') ?? $request->user()->store;
        $catalog = $this->productService->getVendorCatalog($store, $request);

        return response_success('Catalog retrieved successfully.', new VendorProductCatalogResource($catalog));
    }

    /**
     * Create product via 4-step wizard
     */
    public function store(StoreVendorProductRequest $request): JsonResponse
    {
        $store = $request->attributes->get('store') ?? $request->user()->store;
        $product = $this->productService->storeProduct($store, $request->validated(), $request);

        return response_success('Product published successfully.', new VendorProductCardResource($product), 201);
    }

    /**
     * Show product details
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        $store = $request->attributes->get('store') ?? $request->user()->store;
        if ($product->store_id !== $store->id) {
            return response_error('Unauthorized access to product.', [], 403);
        }

        return response_success('Product details retrieved.', new VendorProductCardResource($product->load(['category', 'brand', 'variants'])));
    }

    /**
     * Update product details
     */
    public function update(UpdateVendorProductRequest $request, Product $product): JsonResponse
    {
        $store = $request->attributes->get('store') ?? $request->user()->store;
        if ($product->store_id !== $store->id) {
            return response_error('Unauthorized access to product.', [], 403);
        }

        $updatedProduct = $this->productService->updateProduct($product, $request->validated(), $request);

        return response_success('Product updated successfully.', new VendorProductCardResource($updatedProduct));
    }

    /**
     * Quick inventory stock adjustment
     */
    public function updateStock(UpdateProductStockRequest $request, Product $product): JsonResponse
    {
        $store = $request->attributes->get('store') ?? $request->user()->store;
        if ($product->store_id !== $store->id) {
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
        $store = $request->attributes->get('store') ?? $request->user()->store;
        if ($product->store_id !== $store->id) {
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
        $store = $request->attributes->get('store') ?? $request->user()->store;
        if ($product->store_id !== $store->id) {
            return response_error('Unauthorized access to product.', [], 403);
        }

        $this->productService->deleteProduct($product);

        return response_success('Product deleted successfully.', []);
    }
}
