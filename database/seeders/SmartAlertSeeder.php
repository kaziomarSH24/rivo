<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Pet;
use App\Models\WalkSession;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SmartAlertSeeder extends Seeder
{
    public function run(): void
    {
        // Get the first pet to seed data for
        $pet = Pet::first();
        if (!$pet) {
            $this->command->warn('No pets found to seed smart alert data. Run DatabaseSeeder first.');
            return;
        }

        $this->command->info("Seeding Smart Alert trigger data for Pet ID: {$pet->id}");

        // 1. Seed upcoming vaccination (within 14 days)
        Appointment::updateOrCreate(
            [
                'pet_id' => $pet->id,
                'title' => 'Annual Rabies Booster',
            ],
            [
                'user_id' => $pet->user_id,
                'type' => 'vaccination',
                'datetime' => Carbon::now()->addDays(5)->setTime(10, 0),
                'status' => 'upcoming',
            ]
        );

        // 2. Seed WalkSession data (High activity last week, low activity this week)
        // Last week (8-14 days ago) - High Activity (e.g. 5000 steps per day)
        for ($i = 8; $i <= 14; $i++) {
            WalkSession::create([
                'pet_id' => $pet->id,
                'distance_km' => 3.5,
                'duration_seconds' => 3600,
                'steps' => 5000,
                'start_time' => Carbon::now()->subDays($i)->setTime(8, 0),
                'end_time' => Carbon::now()->subDays($i)->setTime(9, 0),
            ]);
        }

        // This week (0-7 days ago) - Low Activity (e.g. 1000 steps per day) - representing a huge drop!
        for ($i = 0; $i <= 7; $i++) {
            WalkSession::create([
                'pet_id' => $pet->id,
                'distance_km' => 0.5,
                'duration_seconds' => 900,
                'steps' => 1000,
                'start_time' => Carbon::now()->subDays($i)->setTime(8, 0),
                'end_time' => Carbon::now()->subDays($i)->setTime(8, 15),
            ]);
        }

        $this->command->info('Smart Alert dummy data seeded successfully! Run `php artisan alerts:generate-smart` to test.');
    }
}
