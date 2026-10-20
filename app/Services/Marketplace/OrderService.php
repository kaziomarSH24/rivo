<?php

namespace App\Services\Marketplace;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\BaseService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;

class OrderService extends BaseService
{
    protected string $modelClass = Order::class;

    protected function getAllowedFilters(): array
    {
        return [
            AllowedFilter::exact('user_id'),
            AllowedFilter::exact('store_id'),
            AllowedFilter::exact('status'),
            AllowedFilter::exact('payment_status'),
            AllowedFilter::exact('order_number'),
            AllowedFilter::scope('ongoing'),
            AllowedFilter::scope('completed'),
            AllowedFilter::scope('cancelled'),
        ];
    }

    protected function getAllowedIncludes(): array
    {
        return ['user', 'store', 'address', 'items', 'reviews'];
    }

    protected function getAllowedSorts(): array
    {
        return ['created_at', 'total', 'placed_at'];
    }

    /**
     * Customer Checkout: Place an order from active cart
     */
    public function checkout(User $user, array $data, CartService $cartService): Order
    {
        $cartData = $cartService->getCart($user);
        $cart = Cart::where('user_id', $user->id)->firstOrFail();

        if ($cart->items->isEmpty()) {
            throw new \Exception('Cart is empty. Please add items before checkout.');
        }

        return DB::transaction(function () use ($user, $data, $cartData, $cart, $cartService) {
            $store = $cart->store;
            $address = isset($data['address_id']) ? UserAddress::find($data['address_id']) : null;
            $addressJson = $address ? [
                'street_address' => $address->street_address,
                'house_no' => $address->house_no,
                'landmark' => $address->landmark,
                'city' => $address->city,
                'state' => $address->state,
                'zip_code' => $address->zip_code,
            ] : ($data['delivery_address'] ?? null);

            $orderNumber = 'RIV-' . strtoupper(Str::random(5));

            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $user->id,
                'store_id' => $store->id,
                'address_id' => $address ? $address->id : null,
                'delivery_address_json' => $addressJson,
                'status' => 'new',
                'delivery_type' => $data['delivery_type'] ?? 'express',
                'estimated_delivery_time' => $store->delivery_time_text ?: '~15 min',
                'subtotal' => $cartData['summary']['subtotal'],
                'delivery_fee' => $cartData['summary']['delivery_fee'],
                'tax' => $cartData['summary']['tax'],
                'discount' => $cartData['summary']['discount'],
                'total' => $cartData['summary']['total'],
                'payment_method' => $data['payment_method'] ?? 'mastercard',
                'payment_status' => 'paid',
                'notes' => $data['notes'] ?? null,
                'placed_at' => Carbon::now(),
            ]);

            // Copy items to order_items and decrement stock
            foreach ($cart->items as $item) {
                $product = $item->product;
                $variant = $item->variant;
                $price = $variant && $variant->price ? $variant->price : ($product->discount_price ?: $product->price);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'variant_id' => $variant ? $variant->id : null,
                    'product_name' => $product->name,
                    'variant_flavor' => $variant ? $variant->flavor : null,
                    'variant_size' => $variant ? $variant->size_lbs : null,
                    'price' => $price,
                    'quantity' => $item->quantity,
                    'total' => $price * $item->quantity,
                    'image' => is_array($product->images) && !empty($product->images) ? $product->images[0] : null,
                ]);

                // Decrement inventory stock & increment sales count
                $product->decrement('stock', $item->quantity);
                $product->increment('sales_count', $item->quantity);
            }

            // Clear the cart
            $cartService->clearCart($user);

            return $order->load(['items', 'store', 'user']);
        });
    }

    /**
     * Vendor: Accept an incoming order (moves to 'preparing')
     */
    public function vendorAcceptOrder(Store $store, Order $order): Order
    {
        if ($order->store_id !== $store->id) {
            throw new \Exception('Unauthorized access to this order.');
        }

        $order->status = 'preparing';
        $order->save();

        return $order->load(['items', 'user']);
    }

    /**
     * Vendor: Reject an order (cancels and restores stock)
     */
    public function vendorRejectOrder(Store $store, Order $order, ?string $reason = null): Order
    {
        if ($order->store_id !== $store->id) {
            throw new \Exception('Unauthorized access to this order.');
        }

        return DB::transaction(function () use ($order, $reason) {
            $order->status = 'cancelled';
            $order->cancelled_at = Carbon::now();
            $order->rejection_reason = $reason ?: 'Rejected by store';
            $order->save();

            // Restore product stock
            foreach ($order->items as $item) {
                if ($item->product_id) {
                    Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                    Product::where('id', $item->product_id)->decrement('sales_count', $item->quantity);
                }
            }

            return $order;
        });
    }

    /**
     * Vendor: Update fulfillment stepper status
     */
    public function updateFulfillmentStatus(Store $store, Order $order, string $newStatus): Order
    {
        if ($order->store_id !== $store->id) {
            throw new \Exception('Unauthorized access to this order.');
        }

        $validStatuses = ['preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered', 'cancelled'];
        if (!in_array($newStatus, $validStatuses)) {
            throw new \Exception("Invalid order status: {$newStatus}");
        }

        $order->status = $newStatus;
        if ($newStatus === 'delivered') {
            $order->delivered_at = Carbon::now();
        } elseif ($newStatus === 'cancelled') {
            $order->cancelled_at = Carbon::now();
        }
        $order->save();

        return $order->load(['items', 'user']);
    }

    /**
     * Customer: Cancel an order before shipment
     */
    public function customerCancelOrder(User $user, Order $order, ?string $reason = null): Order
    {
        if ($order->user_id !== $user->id) {
            throw new \Exception('Unauthorized access to this order.');
        }

        if (!in_array($order->status, ['new', 'preparing'])) {
            throw new \Exception("Order cannot be cancelled as it is already '{$order->status}'.");
        }

        return DB::transaction(function () use ($order, $reason) {
            $order->status = 'cancelled';
            $order->cancelled_at = Carbon::now();
            $order->rejection_reason = $reason ?: 'Cancelled by customer';
            $order->save();

            // Restore product stock
            foreach ($order->items as $item) {
                if ($item->product_id) {
                    Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                    Product::where('id', $item->product_id)->decrement('sales_count', $item->quantity);
                }
            }

            return $order->load(['items', 'store']);
        });
    }

    /**
     * Customer: List orders by status tab
     */
    public function getCustomerOrders(User $user, ?string $statusFilter = null)
    {
        $query = Order::where('user_id', $user->id)
            ->with(['store:id,name,logo', 'items'])
            ->latest();

        if ($statusFilter === 'ongoing') {
            $query->whereIn('status', ['new', 'preparing', 'ready_for_pickup', 'out_for_delivery']);
        } elseif ($statusFilter === 'completed') {
            $query->where('status', 'delivered');
        } elseif ($statusFilter === 'cancelled') {
            $query->where('status', 'cancelled');
        }

        return $query->paginate(15);
    }
}