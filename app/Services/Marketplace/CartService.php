<?php

namespace App\Services\Marketplace;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\BaseService;

class CartService extends BaseService
{
    protected string $modelClass = Cart::class;

    protected function getAllowedFilters(): array
    {
        return ['user_id', 'store_id'];
    }

    protected function getAllowedIncludes(): array
    {
        return ['store', 'items.product', 'items.variant'];
    }

    protected function getAllowedSorts(): array
    {
        return ['created_at', 'updated_at'];
    }

    /**
     * Get or create customer cart with complete financial summary
     */
    public function getCart(User $user): array
    {
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        $cart->load([
            'store:id,name,logo,delivery_fee,delivery_time_text',
            'items.product:id,store_id,name,price,discount_price,images,stock',
            'items.variant:id,flavor,size_lbs,price,stock',
        ]);

        $subtotal = 0.0;
        foreach ($cart->items as $item) {
            $price = $item->variant && $item->variant->price ? $item->variant->price : ($item->product->discount_price ?: $item->product->price);
            $subtotal += ($price * $item->quantity);
        }

        // Delivery fee: Free for Rivo Pro users or if subtotal is 0
        $isPro = (bool) ($user->plan_id || $user->hasRole('pro'));
        $deliveryFee = ($subtotal > 0 && !$isPro && $cart->store) ? (float) $cart->store->delivery_fee : 0.0;

        $discount = (float) $cart->discount_amount;
        $tax = round($subtotal * 0.10, 2); // 10% tax
        $total = max(0, round($subtotal + $deliveryFee + $tax - $discount, 2));

        return [
            'cart_id' => $cart->id,
            'store' => $cart->store,
            'items_count' => $cart->items->sum('quantity'),
            'items' => $cart->items,
            'summary' => [
                'subtotal' => round($subtotal, 2),
                'delivery_fee' => round($deliveryFee, 2),
                'is_pro_free_delivery' => $isPro,
                'tax' => $tax,
                'discount' => $discount,
                'coupon_code' => $cart->coupon_code,
                'total' => $total,
            ],
        ];
    }

    /**
     * Add item to cart
     */
    public function addItem(User $user, int $productId, ?int $variantId = null, int $quantity = 1): array
    {
        $product = Product::findOrFail($productId);
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        // If cart has items from another store, reset cart to new store
        if ($cart->store_id && $cart->store_id !== $product->store_id) {
            $cart->items()->delete();
        }
        $cart->store_id = $product->store_id;
        $cart->save();

        // Check if item already exists in cart
        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->where('variant_id', $variantId)
            ->first();

        if ($cartItem) {
            $cartItem->quantity += $quantity;
            $cartItem->save();
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'variant_id' => $variantId,
                'quantity' => max(1, $quantity),
            ]);
        }

        return $this->getCart($user);
    }

    /**
     * Update cart item quantity
     */
    public function updateItemQuantity(User $user, int $cartItemId, int $quantity): array
    {
        $cart = Cart::where('user_id', $user->id)->firstOrFail();
        $item = CartItem::where('cart_id', $cart->id)->where('id', $cartItemId)->firstOrFail();

        if ($quantity <= 0) {
            $item->delete();
        } else {
            $item->quantity = $quantity;
            $item->save();
        }

        return $this->getCart($user);
    }

    /**
     * Remove item from cart
     */
    public function removeItem(User $user, int $cartItemId): array
    {
        $cart = Cart::where('user_id', $user->id)->firstOrFail();
        CartItem::where('cart_id', $cart->id)->where('id', $cartItemId)->delete();

        return $this->getCart($user);
    }

    /**
     * Clear customer cart
     */
    public function clearCart(User $user): void
    {
        $cart = Cart::where('user_id', $user->id)->first();
        if ($cart) {
            $cart->items()->delete();
            $cart->store_id = null;
            $cart->coupon_code = null;
            $cart->discount_amount = 0.00;
            $cart->save();
        }
    }
}