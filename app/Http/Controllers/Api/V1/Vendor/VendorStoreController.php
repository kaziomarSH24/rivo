<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreVendorStoreRequest;
use App\Http\Requests\Vendor\UpdateVendorStoreRequest;
use App\Http\Requests\Vendor\UploadStoreDocumentRequest;
use App\Http\Requests\Vendor\VendorAnalyticsRequest;
use App\Http\Resources\Vendor\StoreDocumentResource;
use App\Http\Resources\Vendor\VendorStoreProfileResource;
use App\Http\Resources\Vendor\VendorDashboardResource;
use App\Http\Resources\Vendor\VendorAnalyticsResource;
use App\Models\Store;
use App\Models\StoreDocument;
use App\Services\Marketplace\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorStoreController extends Controller
{
    protected StoreService $storeService;

    public function __construct(StoreService $storeService)
    {
        $this->storeService = $storeService;
    }

    /**
     * Store Onboarding & KYC setup for authenticated vendor
     */
    public function store(StoreVendorStoreRequest $request): JsonResponse
    {
        $existing = Store::where('user_id', $request->user()->id)->first();
        $store = $this->storeService->registerStore($request->user(), $request->validated(), $request);

        $status = $existing ? 200 : 201;
        $message = $existing ? 'Store profile updated successfully.' : 'Store profile created successfully.';

        return response_success($message, new VendorStoreProfileResource($store), $status);
    }

    /**
     * Get vendor dashboard overview (KPIs, revenue, live orders, low stock)
     */
    public function dashboard(Request $request): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store) {
            return response_error('No store registered for this account.', [], 404);
        }

        $data = $this->storeService->getVendorDashboard($store);

        return response_success('Vendor dashboard retrieved successfully.', new VendorDashboardResource($data));
    }

    /**
     * Get vendor business profile
     */
    public function profile(Request $request): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store) {
            return response_error('No store registered for this account.', [], 404);
        }

        return response_success('Business profile retrieved successfully.', new VendorStoreProfileResource($store->load(['documents'])));
    }

    /**
     * Update business profile
     */
    public function updateProfile(UpdateVendorStoreRequest $request): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store) {
            return response_error('No store registered for this account.', [], 404);
        }

        $updatedStore = $this->storeService->updateStoreProfile($store, $request->validated(), $request);

        return response_success('Business profile updated successfully.', new VendorStoreProfileResource($updatedStore));
    }

    /**
     * Upload additional KYC Document
     */
    public function uploadDocument(UploadStoreDocumentRequest $request): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store) {
            return response_error('No store registered for this account.', [], 404);
        }

        $doc = $this->storeService->uploadDocument($store, $request->validated(), $request);

        return response_success('KYC Document uploaded successfully.', new StoreDocumentResource($doc), 201);
    }

    /**
     * Delete a KYC document
     */
    public function deleteDocument(Request $request, StoreDocument $document): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store || $document->store_id !== $store->id) {
            return response_error('Unauthorized access to this document.', [], 403);
        }

        $this->storeService->deleteDocument($store, $document);

        return response_success('Document removed successfully.', []);
    }

    /**
     * Get vendor sales analytics and best sellers
     */
    public function analytics(VendorAnalyticsRequest $request): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store) {
            return response_error('No store registered for this account.', [], 404);
        }

        $period = $request->input('period', 'week');
        $data = $this->storeService->getVendorAnalytics($store, $period);

        return response_success('Vendor analytics retrieved successfully.', new VendorAnalyticsResource($data));
    }
}