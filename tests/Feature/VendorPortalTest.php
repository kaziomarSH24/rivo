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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
                    'greeting',
                    'store' => [
                        'id', 'name', 'logo', 'rating', 'reviews_count', 'fulfillment_rate', 'avg_prep_time_minutes'
                    ],
                    'order_overview' => [
                        'all', 'new', 'preparing', 'ready', 'done', 'out_for_delivery'
                    ],
                    'today_sales' => [
                        'amount', 'growth_percentage', 'weekly_chart'
                    ],
                    'store_performance' => [
                        'rating', 'reviews_count', 'success_rate', 'avg_time'
                    ],
                    'low_stock_alerts',
                    'recent_orders',
                ]
            ]);

        $this->assertNotEmpty($response->json('data.greeting'));
        $this->assertArrayHasKey('done', $response->json('data.order_overview'));

        // Verify Redis cache tag & key exist for dashboard
        $cacheKey = "vendor:dashboard:{$this->store->id}:" . \Carbon\Carbon::today()->toDateString();
        $this->assertTrue(\Illuminate\Support\Facades\Cache::tags(["stores:{$this->store->id}:dashboard", "stores:{$this->store->id}", 'stores'])->has($cacheKey));

        // 2. Simulate order creation -> AutoClearsCache should instantly flush the dashboard cache!
        $customer = User::factory()->create();
        $newOrder = Order::create([
            'order_number' => 'TEST-CACHE-' . uniqid(),
            'user_id' => $customer->id,
            'store_id' => $this->store->id,
            'status' => 'new',
            'delivery_type' => 'express',
            'subtotal' => 100.00,
            'total' => 100.00,
            'payment_status' => 'paid',
        ]);

        $this->assertFalse(
            \Illuminate\Support\Facades\Cache::tags(["stores:{$this->store->id}:dashboard", "stores:{$this->store->id}", 'stores'])->has($cacheKey),
            'Dashboard cache must be automatically invalidated when a new order is saved'
        );

        // 3. Subsequent dashboard call must fetch fresh data with updated order count
        $freshResponse = $this->actingAs($this->vendorUser, 'sanctum')->getJson('/api/v1/vendor/dashboard');
        $this->assertGreaterThanOrEqual(1, $freshResponse->json('data.order_overview.new'));

        // 4. Update order status -> AutoClearsCache should again instantly flush the cache!
        $newOrder->update(['status' => 'preparing']);

        $this->assertFalse(
            \Illuminate\Support\Facades\Cache::tags(["stores:{$this->store->id}:dashboard", "stores:{$this->store->id}", 'stores'])->has($cacheKey),
            'Dashboard cache must be automatically invalidated when order status is updated'
        );
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

    public function test_vendor_can_retrieve_products_catalog_with_counters_and_status_filtering(): void
    {
        $response = $this->actingAs($this->vendorUser, 'sanctum')->getJson('/api/v1/vendor/products');

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'message' => 'Catalog retrieved successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'counters' => [
                        'all',
                        'active',
                        'draft',
                        'out_of_stock',
                    ],
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                            'sku',
                            'subtitle',
                            'image',
                            'images',
                            'price',
                            'formatted_price',
                            'stock',
                            'stock_status',
                            'stock_label',
                            'status',
                            'status_label',
                            'status_color',
                            'is_active',
                        ]
                    ],
                    'per_page',
                    'total',
                ]
            ]);

        // Test filtering by status
        $activeResponse = $this->actingAs($this->vendorUser, 'sanctum')->getJson('/api/v1/vendor/products?status=active');
        $activeResponse->assertStatus(200);

        // Test search
        $searchResponse = $this->actingAs($this->vendorUser, 'sanctum')->getJson('/api/v1/vendor/products?search=Dog');
        $searchResponse->assertStatus(200);
    }

    public function test_vendor_can_create_and_manage_products(): void
    {
        Storage::fake('public');

        // 1. Warm catalog cache first
        $catalogResponse = $this->actingAs($this->vendorUser, 'sanctum')->getJson('/api/v1/vendor/products');
        $catalogResponse->assertStatus(200);
        $initialTotal = $catalogResponse->json('data.total');

        // 2. Create product via JSON with image URLs & variants
        $createResponse = $this->actingAs($this->vendorUser, 'sanctum')->postJson('/api/v1/vendor/products', [
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'name' => 'Organic Cat Treats',
            'description' => 'Delicious all-natural salmon treats for cats.',
            'price' => 15.00,
            'discount_price' => 12.50,
            'stock' => 20,
            'low_stock_threshold' => 5,
            'pet_type' => 'cat',
            'is_active' => true,
            'is_online' => true,
            'is_instore' => true,
            'images' => ['https://images.unsplash.com/photo-1589924691995-400dc9ecc119?w=800'],
            'variants' => [
                [
                    'flavor' => 'Salmon',
                    'size_lbs' => '5 lbs',
                    'price' => 15.00,
                    'stock' => 20,
                ]
            ],
        ]);

        $createResponse->assertStatus(201)
            ->assertJson([
                'ok' => true,
                'message' => 'Product published successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'slug', 'sku', 'subtitle', 'image', 'images', 'price',
                    'formatted_price', 'original_price', 'formatted_original_price',
                    'stock', 'stock_status', 'stock_label', 'status', 'status_label',
                    'status_color', 'is_active', 'category', 'brand', 'variants',
                ]
            ]);

        $productId = $createResponse->json('data.id');
        $this->assertEquals(12.50, (float) $createResponse->json('data.price'));
        $this->assertEquals(15.00, (float) $createResponse->json('data.original_price'));
        $this->assertCount(1, $createResponse->json('data.variants'));

        // Verify cache was invalidated and new product shows in catalog
        $freshCatalog = $this->actingAs($this->vendorUser, 'sanctum')->getJson('/api/v1/vendor/products');
        $this->assertEquals($initialTotal + 1, $freshCatalog->json('data.total'));

        // 3. Show product details
        $showResponse = $this->actingAs($this->vendorUser, 'sanctum')->getJson("/api/v1/vendor/products/{$productId}");
        $showResponse->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'data' => [
                    'id' => $productId,
                    'name' => 'Organic Cat Treats',
                    'pet_type' => 'cat',
                ]
            ]);

        // 4. Update product details & sync variants
        $updateResponse = $this->actingAs($this->vendorUser, 'sanctum')->postJson("/api/v1/vendor/products/{$productId}", [
            'name' => 'Organic Cat Treats (Updated)',
            'price' => 18.00,
            'variants' => [
                [
                    'flavor' => 'Tuna & Salmon',
                    'size_lbs' => '10 lbs',
                    'price' => 18.00,
                    'stock' => 40,
                ],
                [
                    'flavor' => 'Chicken Feast',
                    'size_lbs' => '5 lbs',
                    'price' => 12.00,
                    'stock' => 15,
                ]
            ],
        ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'message' => 'Product updated successfully.',
            ]);
        $this->assertEquals('Organic Cat Treats (Updated)', $updateResponse->json('data.name'));
        $this->assertCount(2, $updateResponse->json('data.variants'));

        // 5. Quick stock stepper adjustment
        $stockResponse = $this->actingAs($this->vendorUser, 'sanctum')->patchJson("/api/v1/vendor/products/{$productId}/stock", [
            'stock' => 35,
        ]);
        $stockResponse->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'data' => ['stock' => 35]
            ]);

        // 6. Toggle active status
        $toggleResponse = $this->actingAs($this->vendorUser, 'sanctum')->patchJson("/api/v1/vendor/products/{$productId}/toggle-active");
        $toggleResponse->assertStatus(200);
        $this->assertFalse((bool) $toggleResponse->json('data.is_active'));

        // 7. Create product via multipart/form-data with binary image file upload and stringified variants
        $imageFile = UploadedFile::fake()->image('dog_biscuit.jpg', 800, 800);
        $multipartResponse = $this->actingAs($this->vendorUser, 'sanctum')->post('/api/v1/vendor/products', [
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'name' => 'Crunchy Dog Biscuits',
            'price' => 22.00,
            'stock' => 50,
            'pet_type' => 'dog',
            'is_active' => 'true',
            'is_online' => '1',
            'is_instore' => 'true',
            'images' => [$imageFile],
            'variants' => json_encode([
                ['flavor' => 'Peanut Butter', 'size_lbs' => '2 lbs', 'price' => 22.00, 'stock' => 50]
            ]),
        ], ['Accept' => 'application/json']);

        $multipartResponse->assertStatus(201)
            ->assertJson(['ok' => true]);
        $multipartProductId = $multipartResponse->json('data.id');
        $this->assertStringEndsWith('.webp', $multipartResponse->json('data.image'));
        $this->assertCount(1, $multipartResponse->json('data.variants'));

        // 8. Delete product & verify soft delete
        $deleteResponse = $this->actingAs($this->vendorUser, 'sanctum')->deleteJson("/api/v1/vendor/products/{$multipartProductId}");
        $deleteResponse->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'message' => 'Product deleted successfully.',
            ]);
        $this->assertSoftDeleted('products', ['id' => $multipartProductId]);
    }

    public function test_vendor_can_accept_and_fulfill_order_via_stepper(): void
    {
        $customer = User::firstOrCreate(
            ['email' => 'buyer_test@yopmail.com'],
            ['name' => 'Sarah Mitchell', 'password' => bcrypt('password123')]
        );

        $order = Order::create([
            'order_number' => 'ORD-4521',
            'user_id' => $customer->id,
            'store_id' => $this->store->id,
            'status' => 'new',
            'delivery_type' => 'express',
            'subtotal' => 80.51,
            'delivery_fee' => 3.99,
            'tax' => 0.00,
            'total' => 84.50,
            'payment_method' => 'card',
            'payment_status' => 'paid',
        ]);

        // 1. Fetch vendor order queue matching Figma UI pills & card resources
        $queueResponse = $this->actingAs($this->vendorUser, 'sanctum')->getJson('/api/v1/vendor/orders');
        $queueResponse->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'message' => 'Store orders retrieved successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'counters' => [
                        'all', 'today', 'new', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'cancelled',
                    ],
                    'current_page',
                    'data' => [
                        '*' => [
                            'id', 'order_number', 'status', 'status_label', 'status_color',
                            'customer' => ['name', 'initials', 'avatar'],
                            'delivery_type', 'delivery_type_label', 'items_count', 'items_summary',
                            'total', 'formatted_total', 'available_actions',
                        ]
                    ],
                    'total',
                ]
            ]);

        // 2. Fetch specific order details matching Figma Stepper & Fulfillment views
        $detailsResponse = $this->actingAs($this->vendorUser, 'sanctum')->getJson("/api/v1/vendor/orders/{$order->id}");
        $detailsResponse->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'data' => [
                    'order_number' => 'ORD-4521',
                    'status' => 'new',
                    'stepper' => [
                        'current_step' => 1,
                    ],
                    'fulfillment_tracking' => [
                        'badge' => 'Order Received',
                    ],
                ]
            ]);

        // 3. Accept order -> moves to 'preparing' with amber badge
        $acceptResponse = $this->actingAs($this->vendorUser, 'sanctum')->postJson("/api/v1/vendor/orders/{$order->id}/accept");
        $acceptResponse->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'data' => [
                    'status' => 'preparing',
                    'status_label' => 'Preparing',
                    'status_color' => 'amber',
                    'stepper' => ['current_step' => 2],
                ]
            ]);
        $this->assertEquals('preparing', $order->fresh()->status);

        // 4. Update status -> ready_for_pickup
        $readyResponse = $this->actingAs($this->vendorUser, 'sanctum')->patchJson("/api/v1/vendor/orders/{$order->id}/status", [
            'status' => 'ready_for_pickup',
        ]);
        $readyResponse->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'data' => [
                    'status' => 'ready_for_pickup',
                    'status_label' => 'Ready',
                ]
            ]);
        $this->assertEquals('ready_for_pickup', $order->fresh()->status);

        // 5. Update status -> out_for_delivery
        $outResponse = $this->actingAs($this->vendorUser, 'sanctum')->patchJson("/api/v1/vendor/orders/{$order->id}/status", [
            'status' => 'out_for_delivery',
        ]);
        $outResponse->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'data' => [
                    'status' => 'out_for_delivery',
                    'status_label' => 'Out for Delivery',
                ]
            ]);
        $this->assertEquals('out_for_delivery', $order->fresh()->status);

        // 6. Update status -> delivered
        $delResponse = $this->actingAs($this->vendorUser, 'sanctum')->patchJson("/api/v1/vendor/orders/{$order->id}/status", [
            'status' => 'delivered',
        ]);
        $delResponse->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'data' => [
                    'status' => 'delivered',
                    'status_label' => 'Delivered',
                ]
            ]);
        $this->assertEquals('delivered', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->delivered_at);
    }

    public function test_vendor_orders_multitenant_isolation_and_middleware(): void
    {
        // 1. Create second vendor with independent store
        $vendorB = User::factory()->create([
            'email' => 'vendor_b_' . uniqid() . '@yopmail.com',
            'password' => bcrypt('password123'),
        ]);
        $vendorB->assignRole('vendor');

        $storeB = Store::create([
            'user_id' => $vendorB->id,
            'name' => 'Store B Pet Emporium',
            'slug' => 'store-b-' . uniqid(),
            'type' => 'pet_shop',
            'delivery_fee' => 4.00,
            'is_live' => true,
        ]);

        // Create Order belonging to Store A
        $customer = User::factory()->create();
        $orderA = Order::create([
            'order_number' => 'ORD-SECURE-A',
            'user_id' => $customer->id,
            'store_id' => $this->store->id,
            'status' => 'new',
            'delivery_type' => 'delivery',
            'subtotal' => 40.00,
            'total' => 44.00,
        ]);

        // 2. Vendor B attempts to view Store A's order -> 403 Forbidden!
        $unauthorizedShow = $this->actingAs($vendorB, 'sanctum')->getJson("/api/v1/vendor/orders/{$orderA->id}");
        $unauthorizedShow->assertStatus(403);

        // 3. Vendor B attempts to accept Store A's order -> 403 Forbidden!
        $unauthorizedAccept = $this->actingAs($vendorB, 'sanctum')->postJson("/api/v1/vendor/orders/{$orderA->id}/accept");
        $unauthorizedAccept->assertStatus(403);

        // 4. Vendor B attempts to reject Store A's order -> 403 Forbidden!
        $unauthorizedReject = $this->actingAs($vendorB, 'sanctum')->postJson("/api/v1/vendor/orders/{$orderA->id}/reject");
        $unauthorizedReject->assertStatus(403);

        // 5. Vendor B attempts to update status of Store A's order -> 403 Forbidden!
        $unauthorizedStatus = $this->actingAs($vendorB, 'sanctum')->patchJson("/api/v1/vendor/orders/{$orderA->id}/status", [
            'status' => 'preparing'
        ]);
        $unauthorizedStatus->assertStatus(403);

        // 6. Vendor without a store calling vendor routes -> intercepted by EnsureVendorStoreRegistered with 404!
        $userWithoutStore = User::factory()->create();
        $noStoreResponse = $this->actingAs($userWithoutStore, 'sanctum')->getJson('/api/v1/vendor/orders');
        $noStoreResponse->assertStatus(404)
            ->assertJson([
                'ok' => false,
                'message' => 'No store profile associated with this account.',
            ]);

        // 7. Store A legitimate rejection flow
        $rejectResponse = $this->actingAs($this->vendorUser, 'sanctum')->postJson("/api/v1/vendor/orders/{$orderA->id}/reject", [
            'reason' => 'Item currently out of stock at counter',
        ]);
        $rejectResponse->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'message' => 'Order rejected and inventory restored.',
                'data' => [
                    'status' => 'cancelled',
                    'rejection_reason' => 'Item currently out of stock at counter',
                ]
            ]);
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
    public function test_vendor_can_onboard_store_and_safely_re_submit_via_store_endpoint(): void
    {
        Storage::fake('public');

        $newVendor = User::factory()->create([
            'email' => 'new_vendor_' . uniqid() . '@yopmail.com',
            'password' => bcrypt('password123'),
        ]);

        $logoFile = UploadedFile::fake()->image('store_logo.jpg', 500, 500);
        $bannerFile = UploadedFile::fake()->image('store_banner.jpg', 1500, 700);

        // 1. Initial Onboarding via POST /api/v1/vendor/store
        $response = $this->actingAs($newVendor, 'sanctum')->post('/api/v1/vendor/store', [
            'name' => 'Happy Paws Austin',
            'type' => 'pet_shop',
            'owner_name' => 'Alex Happy',
            'phone' => '+15129990000',
            'email' => 'alex@happypaws.com',
            'street_address' => '1200 S Lamar Blvd',
            'city' => 'Austin',
            'state' => 'TX',
            'zip_code' => '78704',
            'delivery_fee' => 4.99,
            'delivery_time_text' => '20-30 min',
            'logo' => $logoFile,
            'banner' => $bannerFile,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201)
            ->assertJson([
                'ok' => true,
                'message' => 'Store profile created successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'type',
                    'owner_name',
                    'phone',
                    'email',
                    'street_address',
                    'city',
                    'state',
                    'zip_code',
                    'logo',
                    'banner',
                    'rating',
                    'reviews_count',
                    'fulfillment_rate',
                    'delivery_fee',
                    'delivery_time_text',
                    'is_verified',
                    'verification_status',
                    'is_live',
                    'documents',
                ]
            ]);

        $storeSlug = $response->json('data.slug');
        $logoUrl = $response->json('data.logo');
        $bannerUrl = $response->json('data.banner');

        $this->assertStringContainsString('stores/logos', $logoUrl);
        $this->assertStringEndsWith('.webp', $logoUrl);
        $this->assertStringContainsString('stores/banners', $bannerUrl);
        $this->assertStringEndsWith('.webp', $bannerUrl);

        // 2. Safe re-submit via POST /api/v1/vendor/store (without uploading new files)
        $reSubmitResponse = $this->actingAs($newVendor, 'sanctum')->post('/api/v1/vendor/store', [
            'name' => 'Happy Paws Austin Updated',
            'type' => 'pet_shop',
            'street_address' => '1200 S Lamar Blvd, Suite B',
            'delivery_fee' => 5.99,
        ], ['Accept' => 'application/json']);

        $reSubmitResponse->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'message' => 'Store profile updated successfully.',
            ]);

        // Verify slug, rating, and images are preserved
        $this->assertEquals($storeSlug, $reSubmitResponse->json('data.slug'));
        $this->assertEquals($logoUrl, $reSubmitResponse->json('data.logo'));
        $this->assertEquals($bannerUrl, $reSubmitResponse->json('data.banner'));
        $this->assertEquals(5.99, (float) $reSubmitResponse->json('data.delivery_fee'));
    }

    public function test_vendor_can_upload_and_delete_kyc_document(): void
    {
        Storage::fake('public');

        $pdfFile = UploadedFile::fake()->create('business_license.pdf', 500, 'application/pdf');

        // 1. Upload KYC Document
        $uploadResponse = $this->actingAs($this->vendorUser, 'sanctum')->post('/api/v1/vendor/documents', [
            'type' => 'business_license',
            'document' => $pdfFile,
        ], ['Accept' => 'application/json']);

        $uploadResponse->assertStatus(201)
            ->assertJson([
                'ok' => true,
                'message' => 'KYC Document uploaded successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'store_id',
                    'type',
                    'file_url',
                    'original_name',
                    'status',
                ]
            ]);

        $docId = $uploadResponse->json('data.id');
        $fileUrl = $uploadResponse->json('data.file_url');
        $this->assertNotNull($fileUrl);

        // 2. Delete KYC Document
        $deleteResponse = $this->actingAs($this->vendorUser, 'sanctum')->deleteJson("/api/v1/vendor/documents/{$docId}");
        $deleteResponse->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'message' => 'Document removed successfully.',
            ]);

        $this->assertDatabaseMissing('store_documents', ['id' => $docId]);
    }
}