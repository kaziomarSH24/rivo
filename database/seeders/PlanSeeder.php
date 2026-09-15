<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Plan::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Free',
                'price' => 0.00,
                'apple_product_id' => null,
                'google_product_id' => null,
                'features' => [
                    '1 pet profile',
                    'Basic walk tracking',
                    'Basic training lessons',
                    'Appointments',
                    'Matching and messages',
                    'Marketplace access',
                ],
            ]
        );

        \App\Models\Plan::updateOrCreate(
            ['id' => 2],
            [
                'name' => 'Rivo Plus',
                'price' => 4.97,
                'apple_product_id' => 'com.rivo.plus.monthly',
                'google_product_id' => 'com.rivo.plus.monthly',
                'features' => [
                    'Everything in Free',
                    'Up to 2 pet profiles',
                    'RivoCare AI access',
                    'Weight tracking',
                    'Vaccination records',
                    'Advanced recommendations',
                    'Improved pet-health insights',
                ],
            ]
        );

        \App\Models\Plan::updateOrCreate(
            ['id' => 3],
            [
                'name' => 'Rivo Pro',
                'price' => 7.97,
                'apple_product_id' => 'com.rivo.pro.monthly',
                'google_product_id' => 'com.rivo.pro.monthly',
                'features' => [
                    'Everything in Rivo Plus',
                    'Up to 3 pet profiles',
                    'Health Vault access',
                    'Advanced AI analysis',
                    'Expanded health history',
                    'Premium profile customization',
                    'Pro badge and priority support',
                ],
            ]
        );
    }
}



