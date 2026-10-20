<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\StoreAddressRequest;
use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAddressController extends Controller
{
    /**
     * List user delivery addresses
     */
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()->orderByDesc('is_default')->latest()->get();

        return response_success('User addresses retrieved successfully.', $addresses);
    }

    /**
     * Save new delivery address
     */
    public function store(StoreAddressRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (!empty($data['is_default'])) {
            $user->addresses()->update(['is_default' => false]);
        } else {
            // First address is default
            if ($user->addresses()->count() === 0) {
                $data['is_default'] = true;
            }
        }

        $address = $user->addresses()->create($data);

        return response_success('Delivery address saved successfully.', $address, 201);
    }

    /**
     * Update existing delivery address
     */
    public function update(StoreAddressRequest $request, UserAddress $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response_error('Unauthorized access to this address.', [], 403);
        }

        $data = $request->validated();
        if (!empty($data['is_default'])) {
            $request->user()->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update($data);

        return response_success('Delivery address updated successfully.', $address);
    }

    /**
     * Set address as default
     */
    public function setDefault(Request $request, UserAddress $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response_error('Unauthorized access to this address.', [], 403);
        }

        $request->user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return response_success('Default delivery address updated.', $address);
    }

    /**
     * Delete saved address
     */
    public function destroy(Request $request, UserAddress $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response_error('Unauthorized access to this address.', [], 403);
        }

        $address->delete();

        return response_success('Delivery address deleted successfully.', []);
    }
}