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

    public function test_marketplace_home_feed_media_urls_are_properly_formatted(): void
    {
        $category = ProductCategory::where('slug', 'test-food')->first();
        if ($category) {
            $category->update(['icon' => 'food.png']);
        }

        $this->store->update([
            'logo' => 'stores/logos/store.png',
            'banner' => 'https://example.com/banner.jpg',
            'is_live' => true,
            'is_verified' => true,
            'verification_status' => 'verified',
        ]);

        $this->product->update([
            'images' => ['products/test.png', 'https://images.unsplash.com/sample'],
            'is_active' => true,
            'stock' => 20,
            'discount_price' => 15.00,
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/marketplace/home');

        $response->assertStatus(200);

        $categories = $response->json('data.categories');
        $this->assertNotEmpty($categories);
        $this->assertStringStartsWith('http', $categories[0]['icon']);
        $this->assertStringContainsString('storage/categories/food.png', $categories[0]['icon']);

        $stores = $response->json('data.popular_stores');
        $this->assertNotEmpty($stores);
        $targetStore = collect($stores)->firstWhere('id', $this->store->id) ?? $stores[0];
        if (!empty($targetStore['logo'])) {
            $this->assertStringStartsWith('http', $targetStore['logo']);
            $this->assertStringContainsString('storage/stores/logos/', $targetStore['logo']);
        }
        if (!empty($targetStore['banner'])) {
            $this->assertStringStartsWith('http', $targetStore['banner']);
        }

        $deals = $response->json('data.top_deals');
        $this->assertNotEmpty($deals);
        // Assert seeded Unsplash image link is preserved as full external URL
        $this->assertStringStartsWith('http', $deals[0]['primary_image']);

        // Assert local storage image path is converted to full storage URL
        $targetProd = collect($deals)->firstWhere('id', $this->product->id);
        $this->assertNotNull($targetProd);
        $this->assertStringStartsWith('http', $targetProd['primary_image']);
        $this->assertStringContainsString('storage/products/test.png', $targetProd['primary_image']);
        $this->assertEquals('https://images.unsplash.com/sample', $targetProd['images'][1]);
    }

    public function test_customer_can_retrieve_categories_list(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/marketplace/categories');

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'message' => 'Product categories retrieved successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'subtitle',
                        'icon',
                        'order_column',
                        'products_count',
                    ]
                ]
            ]);
    }

    public function test_customer_can_retrieve_products_by_category(): void
    {
        $category = ProductCategory::where('slug', 'test-food')->first();

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/v1/marketplace/categories/{$category->id}/products?include=store,brand");

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                            'price',
                            'has_discount',
                            'discount_percentage',
                            'primary_image',
                            'images',
                            'store' => [
                                'id',
                                'name',
                                'slug',
                            ],
                            'brand',
                        ]
                    ],
                    'links',
                    'meta' => [
                        'current_page',
                        'total',
                    ]
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

        $orderData = $checkoutResponse->json('data.order') ?? $checkoutResponse->json('data');
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

    public function test_customer_can_retrieve_stores_list_with_resource_formatting(): void
    {
        $allIncludes = 'products,productsCount,productsExists,documents,documentsCount,documentsExists,reviews,reviewsCount,reviewsExists';

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/v1/marketplace/stores?filter[type]=pet_shop&sort=-rating&include={$allIncludes}");

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'message' => 'Stores list retrieved successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                            'type',
                            'logo',
                            'banner',
                            'rating',
                            'reviews_count',
                            'delivery_fee',
                            'delivery_time_text',
                            'street_address',
                            'city',
                            'is_verified',
                            'is_live',
                            'products',
                            'products_count',
                            'products_exists',
                            'documents',
                            'documents_count',
                            'documents_exists',
                            'reviews',
                            'reviews_exists',
                        ]
                    ],
                    'links',
                    'meta' => [
                        'current_page',
                        'per_page',
                        'total',
                    ],
                ]
            ]);

        // Verify sensitive fields are hidden
        $firstStore = $response->json('data.data.0');
        if ($firstStore) {
            $this->assertArrayNotHasKey('user_id', $firstStore);
            $this->assertArrayNotHasKey('owner_name', $firstStore);
            $this->assertArrayNotHasKey('phone', $firstStore);
            $this->assertArrayNotHasKey('email', $firstStore);
            $this->assertIsBool($firstStore['products_exists']);
            $this->assertIsInt($firstStore['products_count']);
        }
    }

    public function test_customer_can_view_store_profile_and_catalog(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/v1/marketplace/stores/{$this->store->id}");

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'message' => 'Store details retrieved successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'store' => [
                        'id',
                        'name',
                        'slug',
                        'type',
                        'logo',
                        'banner',
                        'rating',
                        'reviews_count',
                        'delivery_fee',
                        'delivery_time_text',
                        'street_address',
                        'city',
                        'is_verified',
                        'is_live',
                    ],
                    'categories' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                            'icon',
                        ]
                    ],
                    'reviews_summary' => [
                        'rating',
                        'reviews_count',
                        'breakdown',
                        'reviews' => [
                            'current_page',
                            'data',
                            'per_page',
                            'total',
                        ],
                    ],
                    'products' => [
                        'data' => [
                            '*' => [
                                'id',
                                'name',
                                'slug',
                                'price',
                                'has_discount',
                                'primary_image',
                            ]
                        ],
                    ],
                ]
            ]);

        // Assert sensitive merchant details are stripped
        $storeData = $response->json('data.store');
        $this->assertArrayNotHasKey('user_id', $storeData);
        $this->assertArrayNotHasKey('owner_name', $storeData);
        $this->assertArrayNotHasKey('phone', $storeData);
        $this->assertArrayNotHasKey('email', $storeData);
        $this->assertArrayNotHasKey('documents', $storeData);
    }
}
