<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use DatabaseTransactions;

    protected User $customer;
    protected Store $store;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::firstOrCreate(
            ['email' => 'test_customer@yopmail.com'],
            ['name' => 'Test Customer', 'password' => bcrypt('password123')]
        );
        if (!$this->customer->hasRole('user')) {
            $this->customer->assignRole('user');
        }

        $vendorUser = User::firstOrCreate(
            ['email' => 'test_vendor_owner@yopmail.com'],
            ['name' => 'Store Owner', 'password' => bcrypt('password123')]
        );
        if (!$vendorUser->hasRole('vendor')) {
            $vendorUser->assignRole('vendor');
        }

        $this->store = Store::firstOrCreate(
            ['user_id' => $vendorUser->id],
            [
                'name' => 'Test Marketplace Store',
                'slug' => 'test-marketplace-store',
                'type' => 'pet_shop',
                'delivery_fee' => 4.99,
                'delivery_time_text' => '15-20 min',
                'is_live' => true,
                'is_verified' => true,
            ]
        );

        $category = ProductCategory::firstOrCreate(
            ['slug' => 'test-food'],
            ['name' => 'Test Food', 'is_active' => true]
        );

        $brand = Brand::firstOrCreate(
            ['slug' => 'test-brand'],
            ['name' => 'Test Brand', 'is_active' => true]
        );

        $this->product = Product::firstOrCreate(
            ['slug' => 'test-dog-kibble'],
            [
                'store_id' => $this->store->id,
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'name' => 'Test Dog Kibble',
                'price' => 25.00,
                'discount_price' => 20.00,
                'stock' => 50,
                'is_active' => true,
                'pet_type' => 'dog',
            ]
        );
    }

    public function test_customer_can_view_marketplace_home_feed(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/marketplace/home');

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'message' => 'Marketplace home feed retrieved successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'delivery_address',
                    'pro_banner',
                    'categories',
                    'popular_stores',
                    'recommended_for_pet',
                    'top_deals',
                ]
            ]);
    }

    public function test_customer_can_search_products_and_stores(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/marketplace/search?q=Dog');

        $response->assertStatus(200)
            ->assertJson(['ok' => true])
            ->assertJsonStructure([
                'data' => [
                    'recent_searches',
                    'popular_tags',
                    'products',
                    'stores',
                ]
            ]);
    }

    public function test_customer_can_add_item_to_cart_and_update_quantity(): void
    {
        // 1. Add item
        $addResponse = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/marketplace/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $addResponse->assertStatus(201)
            ->assertJson(['ok' => true]);

        $cartData = $addResponse->json('data');
        $this->assertNotEmpty($cartData['items']);
        $cartItemId = $cartData['items'][0]['id'];

        // 2. Update quantity
        $updateResponse = $this->actingAs($this->customer, 'sanctum')->patchJson("/api/v1/marketplace/cart/items/{$cartItemId}", [
            'quantity' => 3,
        ]);

        $updateResponse->assertStatus(200);
        $this->assertEquals(3, $updateResponse->json('data.items.0.quantity'));

        // 3. View cart
        $viewResponse = $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/marketplace/cart');
        $viewResponse->assertStatus(200);
        $this->assertEquals(3, $viewResponse->json('data.items_count'));
    }

    public function test_customer_can_checkout_and_place_order(): void
    {
        // Setup address & cart
        $address = UserAddress::create([
            'user_id' => $this->customer->id,
            'title' => 'Home',
            'street_address' => '789 Test Lane',
            'city' => 'Austin',
            'state' => 'TX',
            'zip_code' => '78701',
            'is_default' => true,
        ]);

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/marketplace/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $checkoutResponse = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/marketplace/checkout', [
            'address_id' => $address->id,
            'delivery_type' => 'express',
            'payment_method' => 'mastercard',
            'notes' => 'Leave with receptionist',
        ]);

        $checkoutResponse->assertStatus(201)
            ->assertJson(['ok' => true]);

        $orderData = $checkoutResponse->json('data');
        $this->assertEquals('new', $orderData['status']);
        $this->assertStringStartsWith('RIV-', $orderData['order_number']);

        // Check stock was decremented
        $this->assertEquals(49, $this->product->fresh()->stock);
    }

    public function test_customer_can_cancel_order(): void
    {
        $order = Order::create([
            'order_number' => 'RIV-TEST1',
            'user_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => 'new',
            'delivery_type' => 'standard',
            'subtotal' => 20.00,
            'delivery_fee' => 4.99,
            'tax' => 2.00,
            'total' => 26.99,
            'payment_method' => 'mastercard',
            'payment_status' => 'paid',
        ]);

        $cancelResponse = $this->actingAs($this->customer, 'sanctum')->postJson("/api/v1/marketplace/orders/{$order->id}/cancel", [
            'reason' => 'Ordered by mistake',
        ]);

        $cancelResponse->assertStatus(200);
        $this->assertEquals('cancelled', $order->fresh()->status);
    }

    public function test_customer_can_submit_product_review(): void
    {
        $reviewResponse = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/marketplace/reviews', [
            'reviewable_type' => 'product',
            'reviewable_id' => $this->product->id,
            'rating' => 5,
            'comment' => 'Great product, high quality!',
        ]);

        $reviewResponse->assertStatus(201)
            ->assertJson(['ok' => true]);

        $this->assertEquals(5, $this->product->fresh()->rating);
        $this->assertEquals(1, $this->product->fresh()->reviews_count);
    }

    public function test_customer_can_manage_delivery_addresses(): void
    {
        // 1. Create address
        $createResponse = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/marketplace/addresses', [
            'title' => 'Office',
            'street_address' => '100 Congress Ave',
            'city' => 'Austin',
            'state' => 'TX',
            'zip_code' => '78701',
            'is_default' => true,
        ]);

        $createResponse->assertStatus(201);
        $addressId = $createResponse->json('data.id');

        // 2. List addresses
        $listResponse = $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/marketplace/addresses');
        $listResponse->assertStatus(200);
        $this->assertNotEmpty($listResponse->json('data'));

        // 3. Delete address
        $delResponse = $this->actingAs($this->customer, 'sanctum')->deleteJson("/api/v1/marketplace/addresses/{$addressId}");
        $delResponse->assertStatus(200);
    }
}