<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class VendorPortalTest extends TestCase
{
    use DatabaseTransactions;

    protected User $vendorUser;
    protected Store $store;
    protected ProductCategory $category;
    protected Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendorUser = User::firstOrCreate(
            ['email' => 'vendor_test_user@yopmail.com'],
            ['name' => 'Sarah Vendor', 'password' => bcrypt('password123')]
        );
        if (!$this->vendorUser->hasRole('vendor')) {
            $this->vendorUser->assignRole('vendor');
        }

        $this->store = Store::firstOrCreate(
            ['user_id' => $this->vendorUser->id],
            [
                'name' => 'Sarah Pet Supplies',
                'slug' => 'sarah-pet-supplies',
                'type' => 'pet_shop',
                'owner_name' => 'Sarah Vendor',
                'phone' => '+15125550199',
                'email' => 'vendor_test_user@yopmail.com',
                'delivery_fee' => 3.99,
                'is_live' => true,
                'is_verified' => true,
            ]
        );

        $this->category = ProductCategory::firstOrCreate(
            ['slug' => 'vendor-test-category'],
            ['name' => 'Vendor Test Category', 'is_active' => true]
        );

        $this->brand = Brand::firstOrCreate(
            ['slug' => 'vendor-test-brand'],
            ['name' => 'Vendor Test Brand', 'is_active' => true]
        );
    }

    public function test_vendor_can_view_dashboard_metrics(): void
    {
        $response = $this->actingAs($this->vendorUser, 'sanctum')->getJson('/api/v1/vendor/dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'message' => 'Vendor dashboard retrieved successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'store',
                    'order_overview',
                    'today_sales',
                    'low_stock_alerts',
                    'recent_orders',
                ]
            ]);
    }

    public function test_vendor_can_view_and_update_business_profile(): void
    {
        // 1. View profile
        $viewResponse = $this->actingAs($this->vendorUser, 'sanctum')->getJson('/api/v1/vendor/profile');
        $viewResponse->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'data' => [
                    'name' => 'Sarah Pet Supplies',
                ]
            ]);

        // 2. Update profile
        $updateResponse = $this->actingAs($this->vendorUser, 'sanctum')->postJson('/api/v1/vendor/profile', [
            'name' => 'Sarah Premium Pets',
            'phone' => '+15125558888',
        ]);

        $updateResponse->assertStatus(200);
        $this->assertEquals('Sarah Premium Pets', $this->store->fresh()->name);
    }

    public function test_vendor_can_create_and_manage_products(): void
    {
        // 1. Create product via wizard
        $createResponse = $this->actingAs($this->vendorUser, 'sanctum')->postJson('/api/v1/vendor/products', [
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'name' => 'Organic Cat Treats',
            'price' => 15.00,
            'stock' => 20,
            'pet_type' => 'cat',
            'is_active' => true,
        ]);

        $createResponse->assertStatus(201);
        $productId = $createResponse->json('data.id');

        // 2. Quick stock stepper adjustment
        $stockResponse = $this->actingAs($this->vendorUser, 'sanctum')->patchJson("/api/v1/vendor/products/{$productId}/stock", [
            'stock' => 35,
        ]);

        $stockResponse->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'data' => ['stock' => 35]
            ]);

        // 3. Toggle active
        $toggleResponse = $this->actingAs($this->vendorUser, 'sanctum')->patchJson("/api/v1/vendor/products/{$productId}/toggle-active");
        $toggleResponse->assertStatus(200);
        $this->assertFalse((bool) $toggleResponse->json('data.is_active'));
    }

    public function test_vendor_can_accept_and_fulfill_order_via_stepper(): void
    {
        $customer = User::firstOrCreate(
            ['email' => 'buyer_test@yopmail.com'],
            ['name' => 'Buyer', 'password' => bcrypt('password123')]
        );

        $order = Order::create([
            'order_number' => 'RIV-VEND1',
            'user_id' => $customer->id,
            'store_id' => $this->store->id,
            'status' => 'new',
            'delivery_type' => 'express',
            'subtotal' => 50.00,
            'delivery_fee' => 3.99,
            'tax' => 5.00,
            'total' => 58.99,
            'payment_method' => 'card',
            'payment_status' => 'paid',
        ]);

        // 1. Accept order -> preparing
        $acceptResponse = $this->actingAs($this->vendorUser, 'sanctum')->postJson("/api/v1/vendor/orders/{$order->id}/accept");
        $acceptResponse->assertStatus(200);
        $this->assertEquals('preparing', $order->fresh()->status);

        // 2. Update status -> ready_for_pickup
        $readyResponse = $this->actingAs($this->vendorUser, 'sanctum')->patchJson("/api/v1/vendor/orders/{$order->id}/status", [
            'status' => 'ready_for_pickup',
        ]);
        $readyResponse->assertStatus(200);
        $this->assertEquals('ready_for_pickup', $order->fresh()->status);

        // 3. Update status -> out_for_delivery
        $outResponse = $this->actingAs($this->vendorUser, 'sanctum')->patchJson("/api/v1/vendor/orders/{$order->id}/status", [
            'status' => 'out_for_delivery',
        ]);
        $outResponse->assertStatus(200);
        $this->assertEquals('out_for_delivery', $order->fresh()->status);

        // 4. Update status -> delivered
        $delResponse = $this->actingAs($this->vendorUser, 'sanctum')->patchJson("/api/v1/vendor/orders/{$order->id}/status", [
            'status' => 'delivered',
        ]);
        $delResponse->assertStatus(200);
        $this->assertEquals('delivered', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->delivered_at);
    }

    public function test_vendor_can_view_sales_analytics(): void
    {
        $response = $this->actingAs($this->vendorUser, 'sanctum')->getJson('/api/v1/vendor/analytics?period=week');

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'message' => 'Vendor analytics retrieved successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'total_sales',
                    'total_orders',
                    'completed_orders',
                    'cancelled_orders',
                    'completion_rate',
                    'avg_order_value',
                    'best_sellers',
                ]
            ]);
    }
}