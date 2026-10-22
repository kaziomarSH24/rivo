<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Store;
use App\Models\StoreDocument;
use App\Models\User;
use App\Models\UserAddress;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Categories
        $categoriesData = [
            ['name' => 'Food', 'slug' => 'food', 'icon' => 'food.png', 'order_column' => 1],
            ['name' => 'Treats', 'slug' => 'treats', 'icon' => 'treats.png', 'order_column' => 2],
            ['name' => 'Toys', 'slug' => 'toys', 'icon' => 'toys.png', 'order_column' => 3],
            ['name' => 'Beds', 'slug' => 'beds', 'icon' => 'beds.png', 'order_column' => 4],
            ['name' => 'Grooming', 'slug' => 'grooming', 'icon' => 'grooming.png', 'order_column' => 5],
            ['name' => 'Health', 'slug' => 'health', 'icon' => 'health.png', 'order_column' => 6],
            ['name' => 'Accessories', 'slug' => 'accessories', 'icon' => 'accessories.png', 'order_column' => 7],
        ];

        $categories = [];
        foreach ($categoriesData as $c) {
            $categories[$c['slug']] = ProductCategory::updateOrCreate(
                ['slug' => $c['slug']],
                [
                    'name' => $c['name'],
                    'icon' => $c['icon'],
                    'is_active' => true,
                    'order_column' => $c['order_column'],
                ]
            );
        }

        // 2. Seed Brands
        $brandsData = [
            ['name' => 'Royal Canin', 'slug' => 'royal-canin'],
            ['name' => 'Pedigree', 'slug' => 'pedigree'],
            ['name' => 'Purina', 'slug' => 'purina'],
            ['name' => 'PetSafe', 'slug' => 'petsafe'],
            ['name' => 'Blue Buffalo', 'slug' => 'blue-buffalo'],
        ];

        $brands = [];
        foreach ($brandsData as $b) {
            $brands[$b['slug']] = Brand::updateOrCreate(
                ['slug' => $b['slug']],
                ['name' => $b['name'], 'is_active' => true]
            );
        }

        // 3. Seed Vendor User & Store (Sarah Mitchell -> Pawsome Pet Shop)
        $vendorUser = User::firstOrCreate(
            ['email' => 'sarah.mitchell@yopmail.com'],
            [
                'name' => 'Sarah Mitchell',
                'phone_number' => '+15125550199',
                'country_code' => '+1',
                'password' => Hash::make('11111111'),
                'email_verified_at' => Carbon::now(),
            ]
        );
        if (!$vendorUser->hasRole('vendor')) {
            $vendorUser->assignRole('vendor');
        }

        $pawsomeStore = Store::updateOrCreate(
            ['user_id' => $vendorUser->id],
            [
                'name' => 'Pawsome Pet Shop',
                'slug' => 'pawsome-pet-shop-austin',
                'type' => 'pet_shop',
                'owner_name' => 'Sarah Mitchell',
                'phone' => '+15125550199',
                'email' => 'sarah.mitchell@yopmail.com',
                'street_address' => '2401 S Congress Ave',
                'city' => 'Austin',
                'state' => 'TX',
                'zip_code' => '78704',
                'latitude' => 30.2435,
                'longitude' => -97.7521,
                'rating' => 4.9,
                'reviews_count' => 128,
                'delivery_fee' => 0.00,
                'delivery_time_text' => '15-20 min',
                'is_verified' => true,
                'verification_status' => 'verified',
                'is_live' => true,
                
            ]
        );

        // Store Documents (KYC)
        StoreDocument::firstOrCreate(
            ['store_id' => $pawsomeStore->id, 'type' => 'business_registration_certificate'],
            [
                'file_path' => 'stores/documents/pawsome_reg.pdf',
                'original_name' => 'pawsome_registration_certificate.pdf',
                'status' => 'approved',
                
            ]
        );

        // 4. Seed other stores matching Figma (SuperPet, Vet Pharmacy, Pet Center)
        $superPetStore = Store::firstOrCreate(
            ['slug' => 'superpet-austin'],
            [
                'user_id' => $vendorUser->id,
                'name' => 'SuperPet',
                'type' => 'pet_shop',
                'owner_name' => 'Alex Johnson',
                'phone' => '+15125550188',
                'email' => 'contact@superpet.com',
                'street_address' => '500 E 6th St',
                'city' => 'Austin',
                'state' => 'TX',
                'zip_code' => '78701',
                'latitude' => 30.2672,
                'longitude' => -97.7431,
                'rating' => 4.8,
                'reviews_count' => 230,
                'delivery_fee' => 0.00,
                'delivery_time_text' => '15 min',
                'is_verified' => true,
                'verification_status' => 'verified',
                'is_live' => true,
                
            ]
        );

        $vetPharmacy = Store::firstOrCreate(
            ['slug' => 'vet-pharmacy-austin'],
            [
                'user_id' => $vendorUser->id,
                'name' => 'Vet Pharmacy',
                'type' => 'pharmacy',
                'owner_name' => 'Dr. Lisa Davis',
                'phone' => '+15125550177',
                'email' => 'care@vetpharmacy.com',
                'street_address' => '1100 W 38th St',
                'city' => 'Austin',
                'state' => 'TX',
                'zip_code' => '78705',
                'latitude' => 30.3045,
                'longitude' => -97.7482,
                'rating' => 4.9,
                'reviews_count' => 95,
                'delivery_fee' => 2.99,
                'delivery_time_text' => '25 min',
                'is_verified' => true,
                'verification_status' => 'verified',
                'is_live' => true,
                
            ]
        );

        // 5. Seed Products with Variants
        $p1 = Product::updateOrCreate(
            ['slug' => 'royal-canin-medium-adult-dry-dog-food'],
            [
                'store_id' => $pawsomeStore->id,
                'category_id' => $categories['food']->id,
                'brand_id' => $brands['royal-canin']->id,
                'name' => 'Royal Canin Medium Adult Dry Dog Food',
                'description' => 'Formulated with high-quality proteins and balanced dietary fibers to support optimal digestibility and natural defenses for medium breed adult dogs (21 to 55 lbs).',
                'pet_type' => 'dog',
                'price' => 54.99,
                'discount_price' => 49.99,
                'sku' => 'RC-MED-ADULT',
                'stock' => 45,
                'low_stock_threshold' => 10,
                'is_active' => true,
                'is_online' => true,
                'is_instore' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1589924691995-400dc9ecc119?w=800',
                    'https://images.unsplash.com/photo-1548767797-d8c844163c4c?w=800'
                ],
                'rating' => 4.8,
                'reviews_count' => 84,
                'sales_count' => 210,
            ]
        );

        ProductVariant::firstOrCreate(
            ['product_id' => $p1->id, 'flavor' => 'Chicken & Rice', 'size_lbs' => '30 lbs'],
            ['price' => 54.99, 'stock' => 30]
        );
        ProductVariant::firstOrCreate(
            ['product_id' => $p1->id, 'flavor' => 'Chicken & Rice', 'size_lbs' => '17 lbs'],
            ['price' => 36.99, 'stock' => 15]
        );

        $p2 = Product::updateOrCreate(
            ['slug' => 'tiny-tummies-puppy-formula'],
            [
                'store_id' => $pawsomeStore->id,
                'category_id' => $categories['food']->id,
                'brand_id' => $brands['blue-buffalo']->id,
                'name' => 'Tiny Tummies Puppy Nutrition Formula',
                'description' => 'Gentle formula for growing puppies with DHA and essential fatty acids for brain & eye development.',
                'pet_type' => 'dog',
                'price' => 28.50,
                'discount_price' => 24.99,
                'sku' => 'TT-PUP-FORMULA',
                'stock' => 25,
                'low_stock_threshold' => 5,
                'is_active' => true,
                'is_online' => true,
                'is_instore' => true,
                'images' => ['https://images.unsplash.com/photo-1568640347023-a616a30bc3bd?w=800'],
                'rating' => 4.7,
                'reviews_count' => 42,
                'sales_count' => 115,
            ]
        );

        $p3 = Product::updateOrCreate(
            ['slug' => 'pedigree-dental-chew-sticks'],
            [
                'store_id' => $superPetStore->id,
                'category_id' => $categories['treats']->id,
                'brand_id' => $brands['pedigree']->id,
                'name' => 'Pedigree DentaStix Daily Dental Treats',
                'description' => 'Clinically proven texture reduces plaque and tartar build-up. Triple action daily oral care for medium/large dogs.',
                'pet_type' => 'dog',
                'price' => 14.99,
                'discount_price' => 11.99,
                'sku' => 'PED-DENTASTIX-7',
                'stock' => 80,
                'low_stock_threshold' => 15,
                'is_active' => true,
                'is_online' => true,
                'is_instore' => true,
                'images' => ['https://images.unsplash.com/photo-1576201836106-db1758fd1c97?w=800'],
                'rating' => 4.9,
                'reviews_count' => 156,
                'sales_count' => 480,
            ]
        );

                // Extra Products
        Product::updateOrCreate(
            ['slug' => 'whiskas-ocean-fish-dry-cat-food'],
            [
                'store_id' => $superPetStore->id,
                'category_id' => $categories['food']->id,
                'brand_id' => $brands['purina']->id,
                'name' => 'Whiskas Ocean Fish Adult Dry Cat Food',
                'description' => 'Complete and balanced nutrition with delicious pocket bites crunchy on the outside with a creamy texture on the inside.',
                'pet_type' => 'cat',
                'price' => 24.99,
                'discount_price' => 21.99,
                'sku' => 'WHISK-OCN-FISH',
                'stock' => 50,
                'low_stock_threshold' => 10,
                'is_active' => true,
                'is_online' => true,
                'is_instore' => true,
                'images' => ['https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?w=800'],
                'rating' => 4.6,
                'reviews_count' => 38,
                'sales_count' => 92,
            ]
        );

        Product::updateOrCreate(
            ['slug' => 'smart-auto-pet-feeder-wifi'],
            [
                'store_id' => $superPetStore->id,
                'category_id' => $categories['accessories']->id,
                'brand_id' => $brands['petsafe']->id,
                'name' => 'Smart Automatic Pet Feeder with WiFi & App Control',
                'description' => 'Schedule up to 6 meals daily with portion control and voice message recorder. Works with iOS and Android.',
                'pet_type' => 'both',
                'price' => 89.99,
                'discount_price' => 74.99,
                'sku' => 'PETSAFE-AUTO-FEED',
                'stock' => 18,
                'low_stock_threshold' => 4,
                'is_active' => true,
                'is_online' => true,
                'is_instore' => true,
                'images' => ['https://images.unsplash.com/photo-1544568100-847a948585b9?w=800'],
                'rating' => 4.9,
                'reviews_count' => 64,
                'sales_count' => 75,
            ]
        );

        Product::updateOrCreate(
            ['slug' => 'orthopedic-memory-foam-dog-bed'],
            [
                'store_id' => $pawsomeStore->id,
                'category_id' => $categories['beds']->id,
                'brand_id' => $brands['petsafe']->id,
                'name' => 'Orthopedic Memory Foam Dog Bed with Removable Cover',
                'description' => 'High-density memory foam provides therapeutic comfort for aching joints and hip dysplasia. Water-resistant liner included.',
                'pet_type' => 'dog',
                'price' => 69.99,
                'discount_price' => 59.99,
                'sku' => 'BED-ORTHO-LG',
                'stock' => 12,
                'low_stock_threshold' => 3,
                'is_active' => true,
                'is_online' => true,
                'is_instore' => true,
                'images' => ['https://images.unsplash.com/photo-1541599540903-216a46ca1dc0?w=800'],
                'rating' => 4.9,
                'reviews_count' => 47,
                'sales_count' => 88,
            ]
        );
        // Pharmacy Products for Vet Pharmacy Store
        $pharm1 = Product::updateOrCreate(
            ['slug' => 'frontline-plus-flea-tick-medium-dogs'],
            [
                'store_id' => $vetPharmacy->id,
                'category_id' => $categories['health']->id,
                'brand_id' => $brands['purina']->id,
                'name' => 'Frontline Plus Flea & Tick Treatment for Dogs (23-44 lbs)',
                'description' => 'Fast-acting, long-lasting flea and tick control for medium dogs. Waterproof formula that protects for 30 days.',
                'pet_type' => 'dog',
                'price' => 45.99,
                'discount_price' => 39.99,
                'sku' => 'FL-PLUS-MED',
                'stock' => 50,
                'low_stock_threshold' => 10,
                'is_active' => true,
                'is_online' => true,
                'is_instore' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=800',
                ],
                'rating' => 4.9,
                'reviews_count' => 18,
                'sales_count' => 64,
            ]
        );

        $pharm2 = Product::updateOrCreate(
            ['slug' => 'nutramax-dasuquin-joint-health-supplement'],
            [
                'store_id' => $vetPharmacy->id,
                'category_id' => $categories['health']->id,
                'brand_id' => $brands['royal-canin']->id,
                'name' => 'Nutramax Dasuquin with MSM Joint Health Supplement for Dogs',
                'description' => 'Advanced joint health formula containing glucosamine, chondroitin sulfate, and avocado/soybean unsaponifiables (ASU).',
                'pet_type' => 'dog',
                'price' => 52.50,
                'discount_price' => null,
                'sku' => 'DASUQUIN-MSM-84',
                'stock' => 30,
                'low_stock_threshold' => 5,
                'is_active' => true,
                'is_online' => true,
                'is_instore' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1584017911766-d451b3d0e843?w=800',
                ],
                'rating' => 4.8,
                'reviews_count' => 12,
                'sales_count' => 38,
            ]
        );

        // 6. User Address for kaziomar@yopmail.com
        $customerUser = User::where('email', 'kaziomar@yopmail.com')->first();
        if ($customerUser) {
            UserAddress::firstOrCreate(
                ['user_id' => $customerUser->id, 'street_address' => '124 Main Street'],
                [
                                        'house_no' => 'Apt 4B',
                    'landmark' => 'Near South Congress Bridge',
                    'city' => 'Austin',
                    'state' => 'TX',
                    'zip_code' => '78701',
                    'latitude' => 30.2648,
                    'longitude' => -97.7471,
                    'is_default' => true,
                ]
            );

            // Seed Sample Orders
            $order1 = Order::firstOrCreate(
                ['order_number' => 'RIV-78421'],
                [
                    'user_id' => $customerUser->id,
                    'store_id' => $pawsomeStore->id,
                    'status' => 'preparing',
                    'delivery_type' => 'express',
                    'estimated_delivery_time' => '15-20 min',
                    'subtotal' => 49.99,
                    'delivery_fee' => 0.00,
                    'tax' => 4.12,
                    'discount' => 5.00,
                    'total' => 49.11,
                    'payment_method' => 'mastercard',
                    'payment_status' => 'paid',
                    'placed_at' => Carbon::now()->subMinutes(12),
                ]
            );

            OrderItem::firstOrCreate(
                ['order_id' => $order1->id, 'product_id' => $p1->id],
                [
                    'product_name' => $p1->name,
                    'variant_flavor' => 'Chicken & Rice',
                    'variant_size' => '30 lbs',
                    'price' => 49.99,
                    'quantity' => 1,
                    'total' => 49.99,
                ]
            );

            $order2 = Order::firstOrCreate(
                ['order_number' => 'RIV-62194'],
                [
                    'user_id' => $customerUser->id,
                    'store_id' => $superPetStore->id,
                    'status' => 'delivered',
                    'delivery_type' => 'express',
                    'estimated_delivery_time' => '15 min',
                    'subtotal' => 23.98,
                    'delivery_fee' => 0.00,
                    'tax' => 1.98,
                    'discount' => 0.00,
                    'total' => 25.96,
                    'payment_method' => 'apple_pay',
                    'payment_status' => 'paid',
                    'placed_at' => Carbon::now()->subDays(2),
                    'delivered_at' => Carbon::now()->subDays(2)->addMinutes(18),
                ]
            );

            OrderItem::firstOrCreate(
                ['order_id' => $order2->id, 'product_id' => $p3->id],
                [
                    'product_name' => $p3->name,
                    'price' => 11.99,
                    'quantity' => 2,
                    'total' => 23.98,
                ]
            );

            // Seed Reviews (for both Products and Stores)
            // Product Reviews
            Review::updateOrCreate(
                ['user_id' => $customerUser->id, 'reviewable_type' => 'product', 'reviewable_id' => $p1->id],
                [
                    'order_id' => $order1->id,
                    'rating' => 5,
                    'comment' => 'Pedro absolutely loves this Royal Canin formula! His coat is shinier and he finishes his bowl every time.',
                    'is_anonymous' => false,
                ]
            );

            Review::updateOrCreate(
                ['user_id' => $vendorUser->id, 'reviewable_type' => 'product', 'reviewable_id' => $p1->id],
                [
                    'rating' => 4,
                    'comment' => 'High quality dry dog food, great ingredients and reliable nutrition.',
                    'is_anonymous' => false,
                ]
            );

            Review::updateOrCreate(
                ['user_id' => $customerUser->id, 'reviewable_type' => 'product', 'reviewable_id' => $p2->id],
                [
                    'rating' => 5,
                    'comment' => 'Gentle on our new puppy’s stomach. Great balance of nutrients!',
                    'is_anonymous' => false,
                ]
            );

            Review::updateOrCreate(
                ['user_id' => $customerUser->id, 'reviewable_type' => 'product', 'reviewable_id' => $p3->id],
                [
                    'order_id' => $order2->id,
                    'rating' => 5,
                    'comment' => 'My dog loves these daily dental chew sticks! Noticeable breath improvement.',
                    'is_anonymous' => false,
                ]
            );

            Review::updateOrCreate(
                ['user_id' => $customerUser->id, 'reviewable_type' => 'product', 'reviewable_id' => $pharm1->id],
                [
                    'rating' => 5,
                    'comment' => 'Fast acting flea and tick treatment, totally recommended by our vet.',
                    'is_anonymous' => false,
                ]
            );

            // Store Reviews
            Review::updateOrCreate(
                ['user_id' => $customerUser->id, 'reviewable_type' => 'store', 'reviewable_id' => $pawsomeStore->id],
                [
                    'order_id' => $order1->id,
                    'rating' => 5,
                    'comment' => 'Fantastic local pet shop! Ultra fast 15-minute delivery and great customer care.',
                    'is_anonymous' => false,
                ]
            );

            Review::updateOrCreate(
                ['user_id' => $vendorUser->id, 'reviewable_type' => 'store', 'reviewable_id' => $pawsomeStore->id],
                [
                    'rating' => 5,
                    'comment' => 'Best pet store in South Congress. Always stocked with premium organic feeds.',
                    'is_anonymous' => false,
                ]
            );

            Review::updateOrCreate(
                ['user_id' => $customerUser->id, 'reviewable_type' => 'store', 'reviewable_id' => $superPetStore->id],
                [
                    'order_id' => $order2->id,
                    'rating' => 5,
                    'comment' => 'Great selection of pet accessories and quick fulfillment.',
                    'is_anonymous' => false,
                ]
            );

            Review::updateOrCreate(
                ['user_id' => $customerUser->id, 'reviewable_type' => 'store', 'reviewable_id' => $vetPharmacy->id],
                [
                    'rating' => 5,
                    'comment' => 'Authentic veterinary medicines and supplements. Very knowledgeable pharmacists.',
                    'is_anonymous' => false,
                ]
            );

            // Update rating & reviews_count columns on models to reflect real DB records
            foreach (Store::all() as $st) {
                $count = $st->reviews()->count();
                $avg = $count > 0 ? round($st->reviews()->avg('rating'), 1) : 5.0;
                $st->reviews_count = $count;
                $st->rating = $avg;
                $st->save();
            }

            foreach (Product::all() as $pr) {
                $count = $pr->reviews()->count();
                $avg = $count > 0 ? round($pr->reviews()->avg('rating'), 1) : 5.0;
                $pr->reviews_count = $count;
                $pr->rating = $avg;
                $pr->save();
            }
        }
    }
}
