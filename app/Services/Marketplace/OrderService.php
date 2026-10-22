<?php

namespace App\Services\Marketplace;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\BaseService;
use App\Services\Payment\PaymentGatewayManager;
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
            AllowedFilter::exact('id'),
            AllowedFilter::exact('order_number'),
            AllowedFilter::exact('status'),
            AllowedFilter::exact('delivery_type'),
            AllowedFilter::exact('store_id'),
            AllowedFilter::exact('payment_status'),
            AllowedFilter::scope('ongoing'),
            AllowedFilter::scope('completed'),
            AllowedFilter::scope('cancelled'),
        ];
    }

    protected function getAllowedIncludes(): array
    {
        return ['user', 'store', 'address', 'items', 'reviews', 'transaction', 'transactions'];
    }

    protected function getAllowedSorts(): array
    {
        return ['created_at', 'total', 'placed_at'];
    }

    /**
     * Customer Checkout: Place an order from active cart & process payment via Gateway Interface
     */
    public function checkout(
        User $user,
        array $data,
        CartService $cartService,
        ?PaymentGatewayManager $paymentManager = null
    ): array {
        $cartData = $cartService->getCart($user);
        $cart = Cart::where('user_id', $user->id)->firstOrFail();

        if ($cart->items->isEmpty()) {
            throw new \Exception('Cart is empty. Please add items before checkout.');
        }

        $paymentManager = $paymentManager ?: app(PaymentGatewayManager::class);
        $paymentMethod = $data['payment_method'] ?? 'stripe';

        return DB::transaction(function () use ($user, $data, $cartData, $cart, $cartService, $paymentManager, $paymentMethod) {
            $store = $cart->store;

            // Validate or auto-resolve delivery address
            $address = null;
            if (!empty($data['address_id'])) {
                $address = UserAddress::where('id', $data['address_id'])->where('user_id', $user->id)->first();
                if (!$address) {
                    throw new \Exception('The selected delivery address does not exist or does not belong to your account.');
                }
            } elseif (empty($data['delivery_address'])) {
                // Auto-fallback: User's default address or most recent saved address
                $address = UserAddress::where('user_id', $user->id)->where('is_default', true)->first()
                    ?? UserAddress::where('user_id', $user->id)->latest()->first();
            }

            $deliveryType = $data['delivery_type'] ?? 'express';
            if ($deliveryType !== 'pickup' && !$address && empty($data['delivery_address'])) {
                throw new \Exception('A delivery address is required. Please select or add an address to continue.');
            }

            $addressJson = $address ? [
                'street_address' => $address->street_address,
                'house_no' => $address->house_no,
                'landmark' => $address->landmark,
                'city' => $address->city,
                'state' => $address->state,
                'zip_code' => $address->zip_code,
                'latitude' => $address->latitude,
                'longitude' => $address->longitude,
            ] : ($data['delivery_address'] ?? null);

            // Pre-validate inventory stock for all cart items
            foreach ($cart->items as $item) {
                $product = $item->product;
                $variant = $item->variant;

                if ($variant && $variant->stock < $item->quantity) {
                    throw new \Exception("Item '{$product->name}' ({$variant->flavor}) does not have enough stock (available: {$variant->stock}).");
                } elseif (!$variant && $product->stock < $item->quantity) {
                    throw new \Exception("Item '{$product->name}' does not have enough stock (available: {$product->stock}).");
                }
            }

            $orderNumber = 'RIV-' . strtoupper(Str::random(5));

            $deliveryType = $data['delivery_type'] ?? 'express';
            $isPro = $user->isPro();
            $baseStoreFee = (float) ($store->delivery_fee ?: 4.99);

            if ($deliveryType === 'pickup') {
                $deliveryFee = 0.0;
                $estimatedDeliveryTime = 'Ready for pickup in ~15 min';
            } elseif ($deliveryType === 'standard') {
                $deliveryFee = $isPro ? 0.0 : $baseStoreFee;
                $estimatedDeliveryTime = '~45-60 min';
            } else { // express priority
                $deliveryFee = $isPro ? 0.0 : round($baseStoreFee + 2.99, 2);
                $estimatedDeliveryTime = $store->delivery_time_text ?: '15-20 min';
            }

            $subtotal = (float) $cartData['summary']['subtotal'];
            $discount = (float) $cartData['summary']['discount'];
            $tax = (float) $cartData['summary']['tax'];
            $total = max(0, round($subtotal + $deliveryFee + $tax - $discount, 2));

            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $user->id,
                'store_id' => $store->id,
                'address_id' => $address ? $address->id : null,
                'delivery_address_json' => $addressJson,
                'status' => 'new',
                'delivery_type' => $deliveryType,
                'estimated_delivery_time' => $estimatedDeliveryTime,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'payment_method' => $paymentMethod,
                'payment_status' => 'pending',
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

                // Decrement product & variant inventory stock, increment sales count
                $product->decrement('stock', $item->quantity);
                $product->increment('sales_count', $item->quantity);
                if ($variant) {
                    $variant->decrement('stock', $item->quantity);
                }
            }

            // Clear the cart
            $cartService->clearCart($user);

            // Process payment through the decoupled payment gateway interface
            $gateway = $paymentManager->gateway($paymentMethod);
            $paymentResult = $gateway->processPayment($order, [
                'payment_method_id' => $data['payment_method_id'] ?? null,
            ]);

            if ($paymentResult->status === 'failed') {
                throw new \Exception($paymentResult->message ?: 'Payment initialization failed. Please try again.');
            }

            return [
                'order' => $order->fresh(['items', 'store', 'user', 'address', 'transaction']),
                'payment' => $paymentResult->toArray(),
            ];
        });
    }

    /**
     * Customer: Confirm payment for an order via Payment Gateway Interface
     */
    public function confirmOrderPayment(
        User $user,
        Order $order,
        array $payload = [],
        ?PaymentGatewayManager $paymentManager = null
    ): array {
        if ($order->user_id !== $user->id) {
            throw new \Exception('Unauthorized access to this order.');
        }

        if ($order->payment_status === 'paid') {
            return [
                'success' => true,
                'message' => 'Order is already marked as paid.',
                'order' => $order->load(['items', 'store', 'transaction']),
            ];
        }

        $paymentManager = $paymentManager ?: app(PaymentGatewayManager::class);
        $gateway = $paymentManager->gateway($order->payment_method);

        $isVerified = $gateway->verifyPayment($order, $payload);

        if ($isVerified) {
            $order->update([
                'payment_status' => 'paid',
                'placed_at' => Carbon::now(),
            ]);

            return [
                'success' => true,
                'message' => 'Payment verified and order confirmed successfully.',
                'order' => $order->fresh(['items', 'store', 'user', 'address', 'transaction']),
            ];
        }

        return [
            'success' => false,
            'message' => 'Payment verification failed or status is not succeeded yet.',
            'order' => $order->fresh(['items', 'store', 'transaction']),
        ];
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
     * Vendor: Reject an order (cancels, restores stock, and initiates refund if paid)
     */
    public function vendorRejectOrder(
        Store $store,
        Order $order,
        ?string $reason = null,
        ?PaymentGatewayManager $paymentManager = null
    ): Order {
        if ($order->store_id !== $store->id) {
            throw new \Exception('Unauthorized access to this order.');
        }

        return DB::transaction(function () use ($order, $reason, $paymentManager) {
            $order->status = 'cancelled';
            $order->cancelled_at = Carbon::now();
            $order->rejection_reason = $reason ?: 'Rejected by store';
            $order->save();

            // Restore product & variant stock
            foreach ($order->items as $item) {
                if ($item->product_id) {
                    Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                    Product::where('id', $item->product_id)->where('sales_count', '>=', $item->quantity)->decrement('sales_count', $item->quantity);
                }
                if ($item->variant_id) {
                    ProductVariant::where('id', $item->variant_id)->increment('stock', $item->quantity);
                }
            }

            // If order was already paid, initiate refund via gateway
            if ($order->payment_status === 'paid') {
                try {
                    $manager = $paymentManager ?: app(PaymentGatewayManager::class);
                    $gateway = $manager->gateway($order->payment_method);
                    $gateway->refund($order, $order->total, $reason);
                } catch (\Exception $e) {
                    \Log::error('Auto-refund on rejection failed: ' . $e->getMessage(), ['order_id' => $order->id]);
                }
            }

            return $order->load(['items', 'user', 'transaction']);
        });
    }

    /**
     * Vendor: Update fulfillment stepper status
     */
    public function updateFulfillmentStatus(
        Store $store,
        Order $order,
        string $newStatus,
        ?string $reason = null,
        ?PaymentGatewayManager $paymentManager = null
    ): Order {
        if ($order->store_id !== $store->id) {
            throw new \Exception('Unauthorized access to this order.');
        }

        $validStatuses = ['preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered', 'cancelled'];
        if (!in_array($newStatus, $validStatuses)) {
            throw new \Exception("Invalid order status: {$newStatus}");
        }

        if ($newStatus === 'cancelled') {
            return $this->vendorRejectOrder($store, $order, $reason, $paymentManager);
        }

        $order->status = $newStatus;
        if ($newStatus === 'delivered') {
            $order->delivered_at = Carbon::now();
            // If COD, mark paid upon successful delivery and update transaction
            if ($order->payment_method === 'cash_on_delivery' && $order->payment_status !== 'paid') {
                $order->payment_status = 'paid';
                if ($order->transaction) {
                    $order->transaction->update(['status' => 'success']);
                }
            }
        }
        $order->save();

        return $order->load(['items', 'user', 'transaction']);
    }

    /**
     * Customer: Cancel an order before shipment
     */
    public function customerCancelOrder(
        User $user,
        Order $order,
        ?string $reason = null,
        ?PaymentGatewayManager $paymentManager = null
    ): Order {
        if ($order->user_id !== $user->id) {
            throw new \Exception('Unauthorized access to this order.');
        }

        if (!in_array($order->status, ['new', 'preparing'])) {
            throw new \Exception("Order cannot be cancelled as it is already '{$order->status}'.");
        }

        return DB::transaction(function () use ($order, $reason, $paymentManager) {
            $order->status = 'cancelled';
            $order->cancelled_at = Carbon::now();
            $order->rejection_reason = $reason ?: 'Cancelled by customer';
            $order->save();

            // Restore product & variant stock
            foreach ($order->items as $item) {
                if ($item->product_id) {
                    Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                    Product::where('id', $item->product_id)->where('sales_count', '>=', $item->quantity)->decrement('sales_count', $item->quantity);
                }
                if ($item->variant_id) {
                    ProductVariant::where('id', $item->variant_id)->increment('stock', $item->quantity);
                }
            }

            // Refund if paid
            if ($order->payment_status === 'paid') {
                try {
                    $manager = $paymentManager ?: app(PaymentGatewayManager::class);
                    $gateway = $manager->gateway($order->payment_method);
                    $gateway->refund($order, $order->total, $reason);
                } catch (\Exception $e) {
                    \Log::error('Auto-refund on customer cancel failed: ' . $e->getMessage(), ['order_id' => $order->id]);
                }
            }

            return $order->load(['items', 'store', 'transaction']);
        });
    }

    /**
     * Customer: List orders by status tab
     */
    public function getCustomerOrders(User $user, ?string $statusFilter = null)
    {
        $query = Order::where('user_id', $user->id)
            ->with(['store:id,name,logo', 'items', 'transaction'])
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

