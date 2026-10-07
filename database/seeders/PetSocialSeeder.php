<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Pet;
use App\Models\PetSwipe;
use App\Models\PetConnection;
use Illuminate\Support\Facades\Hash;

class PetSocialSeeder extends Seeder
{
    public function run(): void
    {
        // Clear previous dummy data
        $emails = [
            'sarah@example.com', 'james@example.com', 'elena@example.com', 'david@example.com', 'farhan@example.com',
            'sarah@yopmail.com', 'james@yopmail.com', 'elena@yopmail.com', 'david@yopmail.com', 'farhan@yopmail.com'
        ];
        
        $dummyUsers = User::whereIn('email', $emails)->get();
        foreach ($dummyUsers as $du) {
            PetSwipe::where('pet_id', $du->pets()->pluck('id'))->delete();
            PetConnection::where('pet_id', $du->pets()->pluck('id'))->delete();
            $du->pets()->delete();
            $du->delete();
        }

        // The user's account - just update coordinates if it exists, or create if missing
        User::updateOrCreate(
            ['email' => 'kaziomar@yopmail.com'],
            [
                'name' => 'Omar Faruk',
                'phone_number' => '+1234567896',
                'password' => Hash::make('11111111'),
                'latitude' => 23.8103, // Exact Dhaka
                'longitude' => 90.4125,
                'email_verified_at' => now(),
            ]
        );

        // Coordinates near Dhaka (23.8103, 90.4125)
        $usersData = [
            ['name' => 'Sarah M.', 'email' => 'sarah@yopmail.com', 'lat' => 23.8153, 'lon' => 90.4175], // ~1km
            ['name' => 'James K.', 'email' => 'james@yopmail.com', 'lat' => 23.8223, 'lon' => 90.4225], // ~2km
            ['name' => 'Elena R.', 'email' => 'elena@yopmail.com', 'lat' => 23.7503, 'lon' => 90.3925], // ~7km
            ['name' => 'David M.', 'email' => 'david@yopmail.com', 'lat' => 23.8903, 'lon' => 90.5125], // ~15km
            ['name' => 'Farhan T.', 'email' => 'farhan@yopmail.com', 'lat' => 24.8103, 'lon' => 91.4125], // ~150km (too far)
        ];

        $petsData = [
            [
                'name' => 'Luna', 'breed' => 'Golden Retriever', 'gender' => 'female', 'type' => 'Dog', 
                'age_years' => 3, 'age_months' => 0, 'weight' => 28, 'is_vaccinated' => true,
                'bio' => 'Friendly and energetic - loves playing fetch, swimming, and long walks in the park.',
                'traits' => ['Vaccinated', 'Friendly', 'High Energy', 'Verified'],
                'personality' => ['Playful', 'Loyal', 'Gentle'],
                'energy_level' => 'High', 'training_level' => 'Advanced'
            ],
            [
                'name' => 'Max', 'breed' => 'Labrador', 'gender' => 'male', 'type' => 'Dog', 
                'age_years' => 4, 'age_months' => 2, 'weight' => 30, 'is_vaccinated' => true,
                'bio' => 'Water dog who loves fetch.',
                'traits' => ['Vaccinated', 'Friendly', 'Swimmer'],
                'personality' => ['Loyal', 'Active', 'Gentle'],
                'energy_level' => 'Medium', 'training_level' => 'Intermediate'
            ],
            [
                'name' => 'Charlie', 'breed' => 'Pug', 'gender' => 'male', 'type' => 'Dog', 
                'age_years' => 0, 'age_months' => 8, 'weight' => 8, 'is_vaccinated' => false,
                'bio' => 'A tiny puppy with a big heart.',
                'traits' => ['Puppy', 'Cute', 'Sleepy'],
                'personality' => ['Lazy', 'Cuddly'],
                'energy_level' => 'Low', 'training_level' => 'Beginner'
            ],
            [
                'name' => 'Bella', 'breed' => 'Persian', 'gender' => 'female', 'type' => 'Cat', 
                'age_years' => 2, 'age_months' => 6, 'weight' => 4, 'is_vaccinated' => true,
                'bio' => 'Loves sleeping by the window.',
                'traits' => ['Indoor', 'Quiet'],
                'personality' => ['Independent', 'Calm'],
                'energy_level' => 'Low', 'training_level' => 'None'
            ],
            [
                'name' => 'Rocky', 'breed' => 'German Shepherd', 'gender' => 'male', 'type' => 'Dog', 
                'age_years' => 8, 'age_months' => 0, 'weight' => 35, 'is_vaccinated' => true,
                'bio' => 'A senior dog who protects the house.',
                'traits' => ['Protective', 'Senior', 'Smart'],
                'personality' => ['Brave', 'Loyal'],
                'energy_level' => 'High', 'training_level' => 'Advanced'
            ],
        ];

        foreach ($usersData as $index => $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('11111111'),
                    'phone_number' => '0170000000' . $index,
                    'country_code' => '+880',
                    'latitude' => $userData['lat'],
                    'longitude' => $userData['lon'],
                    'email_verified_at' => now(),
                ]
            );

            $petData = $petsData[$index];
            Pet::firstOrCreate(
                ['user_id' => $user->id, 'name' => $petData['name']],
                $petData
            );
        }
    }
}
