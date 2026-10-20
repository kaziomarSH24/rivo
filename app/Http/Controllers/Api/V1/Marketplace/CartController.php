<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\AddToCartRequest;
use App\Http\Requests\Marketplace\UpdateCartItemRequest;
use App\Models\CartItem;
use App\Services\Marketplace\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    protected CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Get active cart with live pricing, single-store validation & Pro free delivery calculation
     */
    public function index(Request $request): JsonResponse
    {
        $cart = $this->cartService->getCart($request->user());

        return response_success('Cart retrieved successfully.', $cart);
    }

    /**
     * Add item to cart
     */
    public function addItem(AddToCartRequest $request): JsonResponse
    {
        try {
            $cart = $this->cartService->addItem(
                $request->user(),
                (int) $request->input('product_id'),
                $request->input('variant_id') ? (int) $request->input('variant_id') : null,
                (int) ($request->input('quantity') ?: 1)
            );

            return response_success('Item added to cart.', $cart, 201);
        } catch (\Exception $e) {
            return response_error($e->getMessage(), [], 422);
        }
    }

    /**
     * Update quantity of a cart item
     */
    public function updateItem(UpdateCartItemRequest $request, CartItem $item): JsonResponse
    {
        try {
            $cart = $this->cartService->updateItemQuantity(
                $request->user(),
                $item->id,
                (int) $request->input('quantity')
            );

            return response_success('Cart item updated.', $cart);
        } catch (\Exception $e) {
            return response_error($e->getMessage(), [], 422);
        }
    }

    /**
     * Remove an item from the cart
     */
    public function removeItem(Request $request, CartItem $item): JsonResponse
    {
        try {
            $cart = $this->cartService->removeItem($request->user(), $item->id);

            return response_success('Item removed from cart.', $cart);
        } catch (\Exception $e) {
            return response_error($e->getMessage(), [], 422);
        }
    }

    /**
     * Clear entire cart
     */
    public function clear(Request $request): JsonResponse
    {
        $this->cartService->clearCart($request->user());

        return response_success('Cart cleared successfully.', []);
    }
}