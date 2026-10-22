<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\StoreAddressRequest;
use App\Http\Resources\Marketplace\UserAddressResource;
use App\Models\UserAddress;
use App\Services\Marketplace\UserAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAddressController extends Controller
{
    protected UserAddressService $addressService;

    public function __construct(UserAddressService $addressService)
    {
        $this->addressService = $addressService;
    }

    /**
     * List user delivery addresses
     */
    public function index(Request $request): JsonResponse
    {
        $addresses = $this->addressService->getUserAddresses($request->user());

        return response_success('User addresses retrieved successfully.', UserAddressResource::collection($addresses));
    }

    /**
     * Save new delivery address
     */
    public function store(StoreAddressRequest $request): JsonResponse
    {
        $address = $this->addressService->storeAddress($request->user(), $request->validated());

        return response_success('Delivery address saved successfully.', new UserAddressResource($address), 201);
    }

    /**
     * Update existing delivery address
     */
    public function update(StoreAddressRequest $request, UserAddress $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response_error('Unauthorized access to this address.', [], 403);
        }

        $updated = $this->addressService->updateAddress($request->user(), $address, $request->validated());

        return response_success('Delivery address updated successfully.', new UserAddressResource($updated));
    }

    /**
     * Set address as default
     */
    public function setDefault(Request $request, UserAddress $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response_error('Unauthorized access to this address.', [], 403);
        }

        $updated = $this->addressService->setDefaultAddress($request->user(), $address);

        return response_success('Default delivery address updated.', new UserAddressResource($updated));
    }

    /**
     * Delete saved address
     */
    public function destroy(Request $request, UserAddress $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response_error('Unauthorized access to this address.', [], 403);
        }

        $this->addressService->deleteAddress($request->user(), $address);

        return response_success('Delivery address deleted successfully.', []);
    }
}
