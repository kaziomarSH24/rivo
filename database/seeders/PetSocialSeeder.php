<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Pet;
use App\Models\PetSwipe;
use App\Models\PetConnection;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class PetSocialSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Primary User: Omar Faruk
        $omar = User::updateOrCreate(
            ['email' => 'kaziomar@yopmail.com'],
            [
                'name' => 'Omar Faruk',
                'phone_number' => '+8801711000000',
                'country_code' => '+880',
                'password' => Hash::make('11111111'),
                'latitude' => 23.8103, // Gulshan / Banani, Dhaka
                'longitude' => 90.4125,
                'email_verified_at' => now(),
                'plan_id' => 2, // Rivo Plus
            ]
        );
        $omar->assignRole('user');

        // Primary Pet: Pedro
        $pedro = Pet::updateOrCreate(
            ['user_id' => $omar->id, 'name' => 'Pedro'],
            [
                'type' => 'Dog',
                'breed' => 'Golden Retriever',
                'gender' => 'Male',
                'age_years' => 2,
                'age_months' => 3,
                'weight' => 20.4,
                'is_vaccinated' => true,
                'is_lost' => false,
                'bio' => 'Adventure buddy who loves hiking, park fetch, and trail running.',
                'traits' => ['Vaccinated', 'Friendly', 'High Energy', 'Verified'],
                'personality' => ['Loyal', 'Playful', 'Active', 'Gentle'],
                'energy_level' => 'High',
                'training_level' => 'Advanced',
            ]
        );

        // Secondary Pet: Milo
        $milo = Pet::updateOrCreate(
            ['user_id' => $omar->id, 'name' => 'Milo'],
            [
                'type' => 'Cat',
                'breed' => 'British Shorthair',
                'gender' => 'Male',
                'age_years' => 1,
                'age_months' => 6,
                'weight' => 4.5,
                'is_vaccinated' => true,
                'is_lost' => false,
                'bio' => 'Curious and calm indoor kitty who loves napping by the window.',
                'traits' => ['Indoor', 'Calm', 'Vaccinated'],
                'personality' => ['Independent', 'Gentle', 'Cuddly'],
                'energy_level' => 'Low',
                'training_level' => 'Beginner',
            ]
        );

        // 2. Other Community Users (all @yopmail.com with password 11111111)
        $communityData = [
            [
                'user' => [
                    'name' => 'Sarah M.',
                    'email' => 'sarah@yopmail.com',
                    'phone' => '+8801711000001',
                    'lat' => 23.8153, // ~0.8 km away
                    'lon' => 90.4175,
                ],
                'pet' => [
                    'name' => 'Luna',
                    'type' => 'Dog',
                    'breed' => 'Golden Retriever',
                    'gender' => 'Female',
                    'age_years' => 3,
                    'age_months' => 0,
                    'weight' => 28.0,
                    'is_vaccinated' => true,
                    'is_lost' => false,
                    'bio' => 'Friendly and energetic - loves playing fetch, swimming, and long sunrise walks.',
                    'traits' => ['Vaccinated', 'Friendly', 'High Energy', 'Verified'],
                    'personality' => ['Playful', 'Loyal', 'Gentle'],
                    'energy_level' => 'High',
                    'training_level' => 'Advanced',
                ],
            ],
            [
                'user' => [
                    'name' => 'James K.',
                    'email' => 'james@yopmail.com',
                    'phone' => '+8801711000002',
                    'lat' => 23.8223, // ~1.8 km away
                    'lon' => 90.4225,
                ],
                'pet' => [
                    'name' => 'Max',
                    'type' => 'Dog',
                    'breed' => 'Labrador Retriever',
                    'gender' => 'Male',
                    'age_years' => 4,
                    'age_months' => 2,
                    'weight' => 30.5,
                    'is_vaccinated' => true,
                    'is_lost' => false,
                    'bio' => 'Gentle water dog who loves fetching balls and trail hiking.',
                    'traits' => ['Vaccinated', 'Friendly', 'Swimmer'],
                    'personality' => ['Loyal', 'Active', 'Gentle'],
                    'energy_level' => 'Medium',
                    'training_level' => 'Intermediate',
                ],
            ],
            [
                'user' => [
                    'name' => 'Elena R.',
                    'email' => 'elena@yopmail.com',
                    'phone' => '+8801711000003',
                    'lat' => 23.7503, // ~7 km away (Dhanmondi)
                    'lon' => 90.3925,
                ],
                'pet' => [
                    'name' => 'Charlie',
                    'type' => 'Dog',
                    'breed' => 'French Bulldog',
                    'gender' => 'Male',
                    'age_years' => 1,
                    'age_months' => 2,
                    'weight' => 12.0,
                    'is_vaccinated' => true,
                    'is_lost' => false,
                    'bio' => 'A tiny bulldog with a huge personality and love for tummy rubs.',
                    'traits' => ['Puppy', 'Cute', 'Social'],
                    'personality' => ['Playful', 'Cuddly', 'Sociable'],
                    'energy_level' => 'Low',
                    'training_level' => 'Beginner',
                ],
            ],
            [
                'user' => [
                    'name' => 'David M.',
                    'email' => 'david@yopmail.com',
                    'phone' => '+8801711000004',
                    'lat' => 23.8903, // ~12 km away (Uttara)
                    'lon' => 90.5125,
                ],
                'pet' => [
                    'name' => 'Bella',
                    'type' => 'Dog',
                    'breed' => 'Golden Doodle',
                    'gender' => 'Female',
                    'age_years' => 2,
                    'age_months' => 6,
                    'weight' => 18.0,
                    'is_vaccinated' => true,
                    'is_lost' => false,
                    'bio' => 'Smart and fluffy doodle who loves agility games and meeting puppy pals.',
                    'traits' => ['Hypoallergenic', 'Smart', 'Gentle', 'Vaccinated'],
                    'personality' => ['Friendly', 'Calm', 'Affectionate'],
                    'energy_level' => 'Medium',
                    'training_level' => 'Intermediate',
                ],
            ],
            [
                'user' => [
                    'name' => 'Alex P.',
                    'email' => 'alex@yopmail.com',
                    'phone' => '+8801711000005',
                    'lat' => 23.7903, // ~2.5 km away (Mohakhali)
                    'lon' => 90.4025,
                ],
                'pet' => [
                    'name' => 'Rocky',
                    'type' => 'Dog',
                    'breed' => 'German Shepherd',
                    'gender' => 'Male',
                    'age_years' => 3,
                    'age_months' => 5,
                    'weight' => 35.0,
                    'is_vaccinated' => true,
                    'is_lost' => false,
                    'bio' => 'Loyal and protective companion who excels at frisbee and obedience.',
                    'traits' => ['Protective', 'Smart', 'Athletic', 'Vaccinated'],
                    'personality' => ['Brave', 'Loyal', 'Alert'],
                    'energy_level' => 'High',
                    'training_level' => 'Advanced',
                ],
            ],
            [
                'user' => [
                    'name' => 'Mehedi H.',
                    'email' => 'mehedi@yopmail.com',
                    'phone' => '+8801711000006',
                    'lat' => 23.8303, // ~2.2 km away (Baridhara)
                    'lon' => 90.4150,
                ],
                'pet' => [
                    'name' => 'Simba',
                    'type' => 'Dog',
                    'breed' => 'Siberian Husky',
                    'gender' => 'Male',
                    'age_years' => 2,
                    'age_months' => 0,
                    'weight' => 25.0,
                    'is_vaccinated' => true,
                    'is_lost' => false,
                    'bio' => 'Vocal and joyful sprint champion always ready for a snowy or leafy run.',
                    'traits' => ['High Energy', 'Vocal', 'Fast', 'Vaccinated'],
                    'personality' => ['Curious', 'Playful', 'Extroverted'],
                    'energy_level' => 'High',
                    'training_level' => 'Intermediate',
                ],
            ],
        ];

        $createdPets = [];

        foreach ($communityData as $item) {
            $u = User::updateOrCreate(
                ['email' => $item['user']['email']],
                [
                    'name' => $item['user']['name'],
                    'phone_number' => $item['user']['phone'],
                    'country_code' => '+880',
                    'password' => Hash::make('11111111'),
                    'latitude' => $item['user']['lat'],
                    'longitude' => $item['user']['lon'],
                    'email_verified_at' => now(),
                    'plan_id' => 1,
                ]
            );
            $u->assignRole('user');

            $p = Pet::updateOrCreate(
                ['user_id' => $u->id, 'name' => $item['pet']['name']],
                $item['pet']
            );

            $createdPets[$item['pet']['name']] = $p;
        }

        $luna = $createdPets['Luna'];
        $max = $createdPets['Max'];
        $charlie = $createdPets['Charlie'];
        $bella = $createdPets['Bella'];

        // 3. Social Swipes & Matches
        // Match 1: Pedro & Luna (Mutual Swipe -> Match & Conversation)
        PetSwipe::updateOrCreate(
            ['pet_id' => $pedro->id, 'target_pet_id' => $luna->id],
            ['action' => 'liked']
        );
        PetSwipe::updateOrCreate(
            ['pet_id' => $luna->id, 'target_pet_id' => $pedro->id],
            ['action' => 'liked']
        );

        // Direct conversation between Omar and Sarah
        $conversation = Conversation::findOrCreateDirect($omar->id, $luna->user_id);

        Message::firstOrCreate(
            ['conversation_id' => $conversation->id, 'body' => "You and Sarah matched! Say hello to Luna."],
            ['user_id' => null, 'is_system' => true]
        );
        Message::firstOrCreate(
            ['conversation_id' => $conversation->id, 'body' => "Hi Omar! Pedro looks super handsome. Luna loves playing with Golden Retrievers at Central Park trails!"],
            ['user_id' => $luna->user_id, 'is_system' => false]
        );
        Message::firstOrCreate(
            ['conversation_id' => $conversation->id, 'body' => "Hey Sarah! Pedro is always up for a morning park run. We should set up a dog playdate soon!"],
            ['user_id' => $omar->id, 'is_system' => false]
        );

        PetConnection::updateOrCreate(
            ['pet_id' => $pedro->id, 'connected_pet_id' => $luna->id],
            ['status' => 'accepted', 'source' => 'match', 'conversation_id' => $conversation->id]
        );
        PetConnection::updateOrCreate(
            ['pet_id' => $luna->id, 'connected_pet_id' => $pedro->id],
            ['status' => 'accepted', 'source' => 'match', 'conversation_id' => $conversation->id]
        );

        // Match 2: Pedro & Max (Mutual Match)
        PetSwipe::updateOrCreate(
            ['pet_id' => $pedro->id, 'target_pet_id' => $max->id],
            ['action' => 'liked']
        );
        PetSwipe::updateOrCreate(
            ['pet_id' => $max->id, 'target_pet_id' => $pedro->id],
            ['action' => 'liked']
        );
        PetConnection::updateOrCreate(
            ['pet_id' => $pedro->id, 'connected_pet_id' => $max->id],
            ['status' => 'accepted', 'source' => 'match']
        );
        PetConnection::updateOrCreate(
            ['pet_id' => $max->id, 'connected_pet_id' => $pedro->id],
            ['status' => 'accepted', 'source' => 'match']
        );

        // Request 1: Incoming pending friend request from Charlie to Pedro
        PetConnection::updateOrCreate(
            ['pet_id' => $charlie->id, 'connected_pet_id' => $pedro->id],
            ['status' => 'pending', 'source' => 'direct_request']
        );

        // Request 2: Outgoing pending request from Pedro to Bella
        PetConnection::updateOrCreate(
            ['pet_id' => $pedro->id, 'connected_pet_id' => $bella->id],
            ['status' => 'pending', 'source' => 'direct_request']
        );
    }
}
