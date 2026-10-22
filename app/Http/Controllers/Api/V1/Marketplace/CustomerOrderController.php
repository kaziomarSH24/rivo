<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Resources\Marketplace\OrderCardResource;
use App\Http\Resources\Marketplace\OrderDetailsResource;
use App\Models\Order;
use App\Services\Marketplace\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * List customer orders with tabs support via BaseService Spatie QueryBuilder
     * Example filters: ?filter[status]=ongoing, ?filter[status]=completed, ?filter[status]=cancelled
     */
    public function index(Request $request): JsonResponse
    {
        $orders = $this->orderService->getAll(function ($query) use ($request) {
            $query->where('user_id', $request->user()->id)
                ->with(['store:id,name,slug,logo', 'items']);
        });

        return response_success('Orders retrieved successfully.', OrderCardResource::collection($orders)->response()->getData(true));
    }

    /**
     * View detailed order receipt, tracking stepper & items
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response_error('Unauthorized access to this order.', [], 403);
        }

        $order->load(['store:id,name,slug,logo,phone,street_address', 'items', 'address', 'transaction']);

        return response_success('Order details retrieved successfully.', new OrderDetailsResource($order));
    }

    /**
     * Cancel an ongoing order before it is out for delivery
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        try {
            $reason = $request->input('reason', 'Cancelled by customer');
            $cancelledOrder = $this->orderService->customerCancelOrder($request->user(), $order, $reason);

            return response_success('Order cancelled successfully and inventory restored.', new OrderDetailsResource($cancelledOrder));
        } catch (\Exception $e) {
            return response_error($e->getMessage(), [], 422);
        }
    }
}
