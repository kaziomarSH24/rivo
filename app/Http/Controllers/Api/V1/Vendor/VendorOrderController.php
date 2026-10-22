<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\Marketplace\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorOrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * List incoming and processed orders for vendor's store using BaseService
     */
    public function index(Request $request): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store) {
            return response_error('No store profile associated with this account.', [], 404);
        }

        $orders = $this->orderService->getAll(function ($query) use ($store) {
            $query->where('store_id', $store->id);
        });

        return response_success('Store orders retrieved successfully.', $orders);
    }

    /**
     * View details of a specific store order
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store || $order->store_id !== $store->id) {
            return response_error('Unauthorized access to this order.', [], 403);
        }

        $order->load(['user:id,name,email,avatar', 'items', 'address', 'transaction']);

        return response_success('Order details retrieved successfully.', $order);
    }

    /**
     * Accept a new incoming order (moves status to 'preparing')
     */
    public function accept(Request $request, Order $order): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store || $order->store_id !== $store->id) {
            return response_error('Unauthorized access to this order.', [], 403);
        }

        try {
            $updated = $this->orderService->vendorAcceptOrder($store, $order);
            return response_success('Order accepted and moved to preparing.', $updated);
        } catch (\Exception $e) {
            return response_error($e->getMessage(), [], 422);
        }
    }

    /**
     * Reject an order (re-credits product inventory and marks cancelled)
     */
    public function reject(Request $request, Order $order): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store || $order->store_id !== $store->id) {
            return response_error('Unauthorized access to this order.', [], 403);
        }

        $reason = $request->input('reason', 'Rejected by store');

        try {
            $updated = $this->orderService->vendorRejectOrder($store, $order, $reason);
            return response_success('Order rejected and inventory restored.', $updated);
        } catch (\Exception $e) {
            return response_error($e->getMessage(), [], 422);
        }
    }

    /**
     * Fulfillment stepper transition (preparing -> ready_for_pickup -> out_for_delivery -> delivered)
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $store = $request->user()->store;
        if (!$store || $order->store_id !== $store->id) {
            return response_error('Unauthorized access to this order.', [], 403);
        }

        try {
            $updated = $this->orderService->updateFulfillmentStatus($store, $order, $request->input('status'), $request->input('reason'));
            return response_success('Order fulfillment status updated successfully.', $updated);
        } catch (\Exception $e) {
            return response_error($e->getMessage(), [], 422);
        }
    }
}
