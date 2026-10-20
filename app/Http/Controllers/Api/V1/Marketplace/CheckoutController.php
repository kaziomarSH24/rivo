<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\CheckoutRequest;
use App\Services\Marketplace\CartService;
use App\Services\Marketplace\OrderService;
use Illuminate\Http\JsonResponse;

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
     * Complete checkout: places order, decrements stock, clears cart & issues order receipt
     */
    public function process(CheckoutRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->checkout(
                $request->user(),
                $request->validated(),
                $this->cartService
            );

            return response_success('Order placed successfully.', $order, 201);
        } catch (\Exception $e) {
            return response_error($e->getMessage(), [], 422);
        }
    }
}