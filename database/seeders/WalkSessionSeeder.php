<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Pet;
use App\Models\WalkRoute;
use App\Models\WalkSession;
use App\Models\PetWalkStat;
use Carbon\Carbon;

class WalkSessionSeeder extends Seeder
{
    public function run()
    {
        // 1. Get Omar and Pedro
        $omar = User::where('email', 'kaziomar@yopmail.com')->first();
        if (!$omar) {
            return;
        }

        $pedro = Pet::where('user_id', $omar->id)->where('name', 'Pedro')->first();
        if (!$pedro) {
            return;
        }

        // 2. Create Realistic Walk Routes in Dhaka
        $routesData = [
            [
                'name' => 'Central Park Trails',
                'distance_km' => 4.20,
                'est_duration_minutes' => 52,
                'is_favorite' => true,
                'route_coordinates' => [
                    ['lat' => 23.8103, 'lng' => 90.4125],
                    ['lat' => 23.8120, 'lng' => 90.4140],
                    ['lat' => 23.8150, 'lng' => 90.4180],
                    ['lat' => 23.8180, 'lng' => 90.4220],
                ]
            ],
            [
                'name' => 'Riverside Loop',
                'distance_km' => 3.20,
                'est_duration_minutes' => 38,
                'is_favorite' => true,
                'route_coordinates' => [
                    ['lat' => 23.8103, 'lng' => 90.4125],
                    ['lat' => 23.8115, 'lng' => 90.4130],
                    ['lat' => 23.8125, 'lng' => 90.4140],
                ]
            ],
            [
                'name' => 'Gulshan Lake Walk',
                'distance_km' => 2.50,
                'est_duration_minutes' => 30,
                'is_favorite' => false,
                'route_coordinates' => [
                    ['lat' => 23.8103, 'lng' => 90.4125],
                    ['lat' => 23.8135, 'lng' => 90.4150],
                    ['lat' => 23.8160, 'lng' => 90.4170],
                ]
            ],
        ];

        $routes = [];
        foreach ($routesData as $data) {
            $routes[] = WalkRoute::updateOrCreate(
                ['user_id' => $omar->id, 'name' => $data['name']],
                $data
            );
        }

        // 3. Generate Walk Sessions for the last 14 days
        // Guarantee walks every day for the last 7 days to form a 7-day streak!
        for ($i = 13; $i >= 0; $i--) {
            // For days 7-13, maybe skip 1 day
            if ($i > 6 && $i % 3 === 0) {
                continue;
            }

            $date = Carbon::now()->subDays($i);
            $route = $routes[$i % count($routes)];

            $startTime = $date->copy()->setTime(7, 30);
            $durationSeconds = $route->est_duration_minutes * 60;
            $distanceKm = $route->distance_km;

            WalkSession::updateOrCreate(
                [
                    'pet_id' => $pedro->id,
                    'start_time' => $startTime,
                ],
                [
                    'route_id' => $route->id,
                    'distance_km' => $distanceKm,
                    'duration_seconds' => $durationSeconds,
                    'calories' => round($distanceKm * 67),
                    'steps' => round($distanceKm * 1285),
                    'avg_speed_kmh' => round($distanceKm / ($durationSeconds / 3600), 2),
                    'end_time' => $startTime->copy()->addSeconds($durationSeconds),
                    'route_coordinates' => $route->route_coordinates,
                    'xp_earned' => round($distanceKm * 20),
                    'created_at' => $startTime,
                    'updated_at' => $startTime,
                ]
            );
        }

        // 4. Update Pet Walk Stats for Pedro
        PetWalkStat::updateOrCreate(
            ['pet_id' => $pedro->id],
            [
                'daily_distance_goal_km' => 3.5,
                'total_xp' => 1450,
                'current_level' => 2,
                'current_streak_days' => 7,
                'longest_streak_days' => 12,
                'last_walk_date' => Carbon::now()->toDateString(),
            ]
        );
    }
}
