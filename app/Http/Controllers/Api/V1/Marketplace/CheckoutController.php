<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\CheckoutRequest;
use App\Http\Resources\Marketplace\OrderDetailsResource;
use App\Models\Order;
use App\Services\Marketplace\CartService;
use App\Services\Marketplace\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    protected OrderService $orderService;
    protected CartService $cartService;

    public function __construct(OrderService $orderService, CartService $cartService)
    {
        $this->orderService = $orderService;
        $this->cartService = $cartService;
    }

    /**
     * Complete checkout: places order, decrements stock, clears cart & issues payment intent
     */
    public function process(CheckoutRequest $request): JsonResponse
    {
        try {
            $result = $this->orderService->checkout(
                $request->user(),
                $request->validated(),
                $this->cartService
            );

            return response_success('Order placed successfully.', [
                'order' => new OrderDetailsResource($result['order']),
                'payment' => $result['payment'],
            ], 201);
        } catch (\Exception $e) {
            return response_error($e->getMessage(), [], 422);
        }
    }

    /**
     * Confirm client-side payment (e.g., after Stripe PaymentSheet or Apple Pay completes)
     */
    public function confirmPayment(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response_error('Unauthorized access to this order.', [], 403);
        }

        try {
            $result = $this->orderService->confirmOrderPayment(
                $request->user(),
                $order,
                $request->all()
            );

            if ($result['success']) {
                return response_success(
                    $result['message'],
                    new OrderDetailsResource($result['order'])
                );
            }

            return response_error($result['message'], ['order' => new OrderDetailsResource($result['order'])], 422);
        } catch (\Exception $e) {
            return response_error($e->getMessage(), [], 422);
        }
    }
}
