<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Pet;
use App\Models\Post;
use App\Models\WalkSession;
use App\Models\PostLike;
use App\Models\PostComment;
use Illuminate\Support\Facades\Hash;

class SocialFeedSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure users exist
        $sarah = User::firstOrCreate(
            ['email' => 'sarah@yopmail.com'],
            [
                'name' => 'Sarah M.',
                'password' => Hash::make('11111111'),
                'latitude' => 23.8153,
                'longitude' => 90.4175,
                'email_verified_at' => now(),
            ]
        );

        $james = User::firstOrCreate(
            ['email' => 'james@yopmail.com'],
            [
                'name' => 'James K.',
                'password' => Hash::make('11111111'),
                'latitude' => 23.8223,
                'longitude' => 90.4225,
                'email_verified_at' => now(),
            ]
        );

        $elena = User::firstOrCreate(
            ['email' => 'elena@yopmail.com'],
            [
                'name' => 'Elena R.',
                'password' => Hash::make('11111111'),
                'latitude' => 23.7503,
                'longitude' => 90.3925,
                'email_verified_at' => now(),
            ]
        );

        $omar = User::firstOrCreate(
            ['email' => 'kaziomar@yopmail.com'],
            [
                'name' => 'Omar Faruk',
                'password' => Hash::make('11111111'),
                'latitude' => 23.8103,
                'longitude' => 90.4125,
                'email_verified_at' => now(),
            ]
        );

        // 2. Ensure pets exist
        $luna = Pet::firstOrCreate(
            ['user_id' => $sarah->id, 'name' => 'Luna'],
            [
                'type' => 'Dog',
                'breed' => 'Golden Retriever',
                'gender' => 'female',
                'age_years' => 3,
                'age_months' => 0,
                'weight' => 28,
                'bio' => 'Friendly and energetic - loves long sunrise walks.',
            ]
        );

        $pedro = Pet::firstOrCreate(
            ['user_id' => $james->id, 'name' => 'Pedro'],
            [
                'type' => 'Dog',
                'breed' => 'Golden Retriever',
                'gender' => 'male',
                'age_years' => 2,
                'age_months' => 3,
                'weight' => 32,
                'bio' => 'Adventure buddy who loves hiking and trail running.',
            ]
        );

        // 3. Card 1: Luna's Sunrise Walk (Walk Post)
        $lunaWalk = WalkSession::create([
            'pet_id' => $luna->id,
            'distance_km' => 4.20,
            'duration_seconds' => 3120, // 52 min
            'avg_speed_kmh' => 4.85,
            'steps' => 5400,
            'calories' => 280,
            'start_time' => now()->subMinutes(70),
            'end_time' => now()->subMinutes(18),
            'route_coordinates' => [
                ['lat' => 23.8103, 'lng' => 90.4125],
                ['lat' => 23.8120, 'lng' => 90.4140],
                ['lat' => 23.8150, 'lng' => 90.4180],
                ['lat' => 23.8180, 'lng' => 90.4220],
            ],
            'xp_earned' => 84,
        ]);

        $post1 = Post::create([
            'user_id' => $sarah->id,
            'pet_id' => $luna->id,
            'title' => 'Luna completed a morning walk',
            'content' => 'Perfect sunrise walk through the park trails 🌅',
            'type' => 'walk_summary',
            'location' => 'Central Park Trails',
            'visibility' => 'public',
            'postable_type' => WalkSession::class,
            'postable_id' => $lunaWalk->id,
            'bones_count' => 14,
            'fires_count' => 6,
            'likes_count' => 20,
            'comments_count' => 2,
            'created_at' => now()->subMinutes(18),
        ]);

        PostComment::create([
            'post_id' => $post1->id,
            'user_id' => $elena->id,
            'content' => "So cute! Love Luna's energy in the morning 😍",
            'created_at' => now()->subMinutes(10),
        ]);

        PostComment::create([
            'post_id' => $post1->id,
            'user_id' => $james->id,
            'content' => 'Great pace! Perfect weather for a long walk 🐾',
            'created_at' => now()->subMinutes(5),
        ]);

        // 4. Card 2: Pedro's Trail Runner (Achievement Post)
        $post2 = Post::create([
            'user_id' => $james->id,
            'pet_id' => $pedro->id,
            'title' => 'Pedro earned Trail Runner',
            'content' => 'So proud - 50 km of trails completed 🎉',
            'type' => 'achievement',
            'location' => 'Lima Dog Park',
            'visibility' => 'public',
            'metadata' => [
                'badge_name' => 'Trail Runner',
                'badge_level' => 'Level 2',
                'badge_icon' => 'medal_bronze_paw',
                'bonus_xp' => 200,
                'total_walks' => 58,
                'distance_km' => 50.0,
                'xp_earned' => 200,
            ],
            'bones_count' => 31,
            'fires_count' => 18,
            'likes_count' => 49,
            'comments_count' => 1,
            'created_at' => now()->subHour(),
        ]);

        PostComment::create([
            'post_id' => $post2->id,
            'user_id' => $sarah->id,
            'content' => 'Way to go Pedro!! Amazing accomplishment 🏆',
            'created_at' => now()->subMinutes(30),
        ]);

        // 5. Card 3: Pedro's 7-Day Walk Streak (Milestone Post)
        $post3 = Post::create([
            'user_id' => $sarah->id,
            'pet_id' => $luna->id,
            'title' => 'Pedro reached a 7-day walk streak! 🔥',
            'content' => 'Consistency is key! Keeping up the daily routine with my favorite buddy 🐾',
            'type' => 'milestone',
            'location' => 'Dhaka Dog Park',
            'visibility' => 'public',
            'metadata' => [
                'badge_name' => 'Walk Streak',
                'badge_level' => 'Level 2',
                'badge_icon' => 'medal_bronze_paw',
                'bonus_xp' => 200,
                'streak_days' => 7,
                'total_walks' => 7,
                'total_km' => 28.4,
            ],
            'bones_count' => 48,
            'fires_count' => 33,
            'likes_count' => 81,
            'comments_count' => 0,
            'created_at' => now()->subHours(2),
        ]);

        // Add a reaction from current user Omar to Post 1
        PostLike::create([
            'post_id' => $post1->id,
            'user_id' => $omar->id,
            'reaction_type' => 'bone',
        ]);
    }
}
