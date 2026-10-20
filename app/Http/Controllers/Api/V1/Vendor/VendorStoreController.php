<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreVendorStoreRequest;
use App\Http\Requests\Vendor\UpdateVendorStoreRequest;
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
        $store = $this->storeService->registerStore($request->user(), $request->validated(), $request);

        return response_success('Store profile created successfully.', $store, 201);
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

        return response_success('Vendor dashboard retrieved successfully.', $data);
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

        return response_success('Business profile retrieved successfully.', $store->load(['documents']));
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

        if ($request->hasFile('logo')) {
            $store->logo = $this->storeService->handleFileUpload($request, 'logo', 'stores/logos', 400, 400, 90, true);
        }
        if ($request->hasFile('banner')) {
            $store->banner = $this->storeService->handleFileUpload($request, 'banner', 'stores/banners', 1200, 600, 90, true);
        }

        $store->update($request->only([
            'name', 'type', 'owner_name', 'phone', 'email',
            'street_address', 'city', 'state', 'zip_code', 'delivery_fee',
            'delivery_time_text', 'is_open',
        ]));

        return response_success('Business profile updated successfully.', $store->fresh());
    }

    /**
     * Upload additional KYC Document
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store) {
            return response_error('No store registered for this account.', [], 404);
        }

        $request->validate([
            'type' => 'required|string|in:business_registration_certificate,business_license,tax_certificate,additional_document',
            'document' => 'required|file|mimes:pdf,jpg,png,jpeg|max:10240',
        ]);

        $file = $request->file('document');
        $filePath = $file->store('stores/documents', 'public');

        $doc = \App\Models\StoreDocument::create([
            'store_id' => $store->id,
            'type' => $request->input('type'),
            'file_path' => $filePath,
            'original_name' => $file->getClientOriginalName(),
            'status' => 'pending',
        ]);

        return response_success('KYC Document uploaded successfully.', $doc, 201);
    }

    /**
     * Delete a KYC document
     */
    public function deleteDocument(Request $request, \App\Models\StoreDocument $document): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store || $document->store_id !== $store->id) {
            return response_error('Unauthorized access to this document.', [], 403);
        }

        $document->delete();

        return response_success('Document removed successfully.', []);
    }

    /**
     * Get vendor sales analytics and best sellers
     */
    public function analytics(Request $request): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store) {
            return response_error('No store registered for this account.', [], 404);
        }

        $period = $request->query('period', 'week');
        $data = $this->storeService->getVendorAnalytics($store, $period);

        return response_success('Vendor analytics retrieved successfully.', $data);
    }
}