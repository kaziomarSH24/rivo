<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\DTO\PaymentResult;
use App\Services\Payment\Gateways\CashOnDeliveryGateway;
use App\Services\Payment\Gateways\StripePaymentGateway;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use DatabaseTransactions;

    protected User $customer;
    protected Store $store;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::firstOrCreate(
            ['email' => 'payment_test_customer@yopmail.com'],
            ['name' => 'Payment Customer', 'password' => bcrypt('password123')]
        );
        if (!$this->customer->hasRole('user')) {
            $this->customer->assignRole('user');
        }

        $vendorUser = User::firstOrCreate(
            ['email' => 'payment_test_vendor@yopmail.com'],
            ['name' => 'Payment Vendor', 'password' => bcrypt('password123')]
        );
        if (!$vendorUser->hasRole('vendor')) {
            $vendorUser->assignRole('vendor');
        }

        $this->store = Store::firstOrCreate(
            ['user_id' => $vendorUser->id],
            [
                'name' => 'Payment Test Store',
                'slug' => 'payment-test-store',
                'type' => 'pet_shop',
                'delivery_fee' => 5.00,
                'is_live' => true,
                'is_verified' => true,
            ]
        );

        $category = ProductCategory::firstOrCreate(
            ['slug' => 'payment-category'],
            ['name' => 'Payment Category', 'is_active' => true]
        );

        $this->product = Product::firstOrCreate(
            ['slug' => 'payment-product'],
            [
                'store_id' => $this->store->id,
                'category_id' => $category->id,
                'name' => 'Payment Product',
                'price' => 30.00,
                'stock' => 10,
                'is_active' => true,
            ]
        );
    }

    public function test_payment_gateway_manager_resolves_supported_drivers(): void
    {
        $manager = app(PaymentGatewayManager::class);

        // Stripe drivers
        $this->assertInstanceOf(StripePaymentGateway::class, $manager->gateway('stripe'));
        $this->assertInstanceOf(StripePaymentGateway::class, $manager->gateway('visa'));
        $this->assertInstanceOf(StripePaymentGateway::class, $manager->gateway('mastercard'));
        $this->assertInstanceOf(StripePaymentGateway::class, $manager->gateway('apple_pay'));
        $this->assertInstanceOf(StripePaymentGateway::class, $manager->gateway('card'));

        // Cash on delivery drivers
        $this->assertInstanceOf(CashOnDeliveryGateway::class, $manager->gateway('cash_on_delivery'));
        $this->assertInstanceOf(CashOnDeliveryGateway::class, $manager->gateway('cod'));
        $this->assertInstanceOf(CashOnDeliveryGateway::class, $manager->gateway('cash'));
    }

    public function test_cash_on_delivery_checkout_processes_order_and_records_transaction(): void
    {
        $address = UserAddress::create([
            'user_id' => $this->customer->id,
            'title' => 'Home',
            'street_address' => '123 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'zip_code' => '78701',
            'is_default' => true,
        ]);

        // Add to cart
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/marketplace/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        // Checkout with cash_on_delivery
        $response = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/marketplace/checkout', [
            'address_id' => $address->id,
            'payment_method' => 'cash_on_delivery',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'ok' => true,
                'data' => [
                    'order' => [
                        'payment_method' => 'cash_on_delivery',
                        'payment_status' => 'pending',
                    ],
                    'payment' => [
                        'gateway' => 'cash_on_delivery',
                        'requires_action' => false,
                        'status' => 'pending',
                    ]
                ]
            ]);

        $orderId = $response->json('data.order.id');
        $order = Order::find($orderId);

        // Verify polymorphic transaction was created
        $this->assertNotNull($order->transaction);
        $this->assertEquals('cash_on_delivery', $order->transaction->gateway);
        $this->assertEquals('pending', $order->transaction->status);
    }

    public function test_customer_can_confirm_order_payment(): void
    {
        $order = Order::create([
            'order_number' => 'RIV-PAYCONF1',
            'user_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => 'new',
            'delivery_type' => 'standard',
            'subtotal' => 30.00,
            'delivery_fee' => 5.00,
            'total' => 35.00,
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')->postJson("/api/v1/marketplace/orders/{$order->id}/confirm-payment");

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'data' => [
                    'payment_status' => 'paid',
                ]
            ]);

        $this->assertEquals('paid', $order->fresh()->payment_status);
    }

    public function test_unauthorized_user_cannot_confirm_other_customer_payment(): void
    {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other_user_' . time() . '@yopmail.com',
            'password' => bcrypt('password123'),
        ]);

        $order = Order::create([
            'order_number' => 'RIV-UNAUTH1',
            'user_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => 'new',
            'delivery_type' => 'standard',
            'subtotal' => 30.00,
            'delivery_fee' => 5.00,
            'total' => 35.00,
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAs($otherUser, 'sanctum')->postJson("/api/v1/marketplace/orders/{$order->id}/confirm-payment");
        $response->assertStatus(422);
    }
}