<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Pet;
use App\Models\WalkRoute;
use App\Models\WalkSession;
use Carbon\Carbon;

class WalkSessionSeeder extends Seeder
{
    public function run()
    {
        // 1. Get or create a User
        $user = User::first() ?? User::factory()->create();

        // 2. Get or create a Pet
        $pet = Pet::where('user_id', $user->id)->first();
        if (!$pet) {
            $pet = Pet::create([
                'user_id' => $user->id,
                'type' => 'Dog',
                'name' => 'Bruno (Test)',
                'breed' => 'Golden Retriever',
                'age_years' => 2,
                'weight' => 12.5,
                'gender' => 'Male'
            ]);
        }

        // 3. Create some Walk Routes
        $routesData = [
            ['name' => 'Riverside Loop', 'distance_km' => 3.2, 'est_duration_minutes' => 38, 'is_favorite' => true],
            ['name' => 'Road Perimeter', 'distance_km' => 2.7, 'est_duration_minutes' => 33, 'is_favorite' => true],
            ['name' => 'Neighborhood Quick', 'distance_km' => 2.1, 'est_duration_minutes' => 26, 'is_favorite' => false],
        ];

        $routes = [];
        foreach ($routesData as $data) {
            $routes[] = WalkRoute::create(array_merge($data, [
                'user_id' => $user->id,
                'route_coordinates' => [
                    ['lat' => 23.8103, 'lng' => 90.4125],
                    ['lat' => 23.8115, 'lng' => 90.4130],
                    ['lat' => 23.8125, 'lng' => 90.4140]
                ]
            ]));
        }

        // 4. Generate Walk Sessions for the last 30 days
        // We will generate random walks for most days to fill up W1, W2, W3, W4 charts.
        for ($i = 30; $i >= 0; $i--) {
            // Randomly skip some days to make it realistic (e.g., skip 20% of days)
            if (rand(1, 100) <= 20) {
                continue; 
            }

            // Decide how many walks on this day (1 or 2)
            $walksToday = rand(1, 2);
            $date = Carbon::now()->subDays($i);

            for ($w = 0; $w < $walksToday; $w++) {
                $route = $routes[array_rand($routes)];
                
                // Random time between 6 AM and 8 PM
                $hour = rand(6, 20);
                $minute = rand(0, 59);
                $startTime = $date->copy()->setTime($hour, $minute);
                
                // Add some variance to distance and duration
                $variance = (rand(-20, 20) / 100); // +/- 20%
                $distance = round($route->distance_km * (1 + $variance), 2);
                $durationSeconds = round(($route->est_duration_minutes * 60) * (1 + $variance));
                
                WalkSession::create([
                    'pet_id' => $pet->id,
                    'route_id' => $route->id,
                    'distance_km' => $distance,
                    'duration_seconds' => $durationSeconds,
                    'calories' => round($distance * 80), // Approx 80 kcal per km
                    'avg_speed_kmh' => round(($distance / ($durationSeconds / 3600)), 1),
                    'start_time' => $startTime,
                    'end_time' => $startTime->copy()->addSeconds($durationSeconds),
                    'route_coordinates' => $route->route_coordinates, // Copy route coordinates
                    'xp_earned' => round($distance * 50),
                    'created_at' => $startTime,
                    'updated_at' => $startTime
                ]);
            }
        }
    }
}

