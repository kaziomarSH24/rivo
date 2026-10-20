<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Pet;
use App\Models\Post;
use App\Models\WalkSession;
use App\Models\Reaction;
use App\Models\PostComment;
use Carbon\Carbon;

class SocialFeedSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Fetch Users
        $omar = User::where('email', 'kaziomar@yopmail.com')->first();
        $sarah = User::where('email', 'sarah@yopmail.com')->first();
        $james = User::where('email', 'james@yopmail.com')->first();
        $elena = User::where('email', 'elena@yopmail.com')->first();
        $david = User::where('email', 'david@yopmail.com')->first();
        $alex = User::where('email', 'alex@yopmail.com')->first();

        if (!$omar || !$sarah) {
            return;
        }

        // Fetch Pets
        $pedro = Pet::where('user_id', $omar->id)->where('name', 'Pedro')->first();
        $luna = Pet::where('user_id', $sarah->id)->where('name', 'Luna')->first();

        // 2. Card 1: Luna's Sunrise Walk (Walk Summary Post)
        $lunaWalk = WalkSession::create([
            'pet_id' => $luna->id,
            'distance_km' => 4.20,
            'duration_seconds' => 3120, // 52 min
            'avg_speed_kmh' => 4.85,
            'steps' => 5400,
            'calories' => 280,
            'start_time' => Carbon::now()->subMinutes(70),
            'end_time' => Carbon::now()->subMinutes(18),
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
            'likes_count' => 36,
            'reaction_counts' => [
                'bone' => 14,
                'fire' => 6,
                'paw' => 5,
                'heart' => 8,
                'clap' => 3,
            ],
            'comments_count' => 2,
            'created_at' => Carbon::now()->subMinutes(18),
        ]);

        $comment1 = PostComment::create([
            'post_id' => $post1->id,
            'user_id' => $elena ? $elena->id : $sarah->id,
            'content' => "So cute! Love Luna's energy in the morning 😍",
            'created_at' => Carbon::now()->subMinutes(12),
        ]);

        PostComment::create([
            'post_id' => $post1->id,
            'user_id' => $james ? $james->id : $sarah->id,
            'content' => "Great pace! Perfect weather for a long walk 🐾",
            'created_at' => Carbon::now()->subMinutes(6),
        ]);

        // Omar reacts with 'bone' on Luna's walk post
        Reaction::create([
            'user_id' => $omar->id,
            'reactable_type' => Post::class,
            'reactable_id' => $post1->id,
            'reaction_type' => 'bone',
        ]);

        // 3. Card 2: Pedro's Trail Runner Achievement Post
        $post2 = Post::create([
            'user_id' => $omar->id,
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
                'total_walks' => 58,
                'distance_km' => 50,
                'xp_earned' => 200,
                'bonus_xp' => 200,
            ],
            'likes_count' => 96,
            'reaction_counts' => [
                'bone' => 31,
                'fire' => 18,
                'paw' => 12,
                'heart' => 20,
                'clap' => 15,
            ],
            'comments_count' => 1,
            'created_at' => Carbon::now()->subHours(1),
        ]);

        PostComment::create([
            'post_id' => $post2->id,
            'user_id' => $james ? $james->id : $sarah->id,
            'content' => "Way to go Pedro!! Amazing accomplishment 🏆",
            'created_at' => Carbon::now()->subMinutes(45),
        ]);

        // Sarah reacts with 'fire' on Pedro's achievement
        Reaction::create([
            'user_id' => $sarah->id,
            'reactable_type' => Post::class,
            'reactable_id' => $post2->id,
            'reaction_type' => 'fire',
        ]);

        // 4. Card 3: Pedro's 7-Day Walk Streak Milestone Post
        $post3 = Post::create([
            'user_id' => $omar->id,
            'pet_id' => $pedro->id,
            'title' => 'Pedro reached a 7-day walk streak! 🔥',
            'content' => 'Consistency is key! Keeping up the daily routine with my favorite buddy 🐾',
            'type' => 'milestone',
            'location' => 'Dhaka Dog Park',
            'visibility' => 'public',
            'metadata' => [
                'badge_name' => 'Walk Streak',
                'badge_level' => 'Level 2',
                'badge_icon' => 'medal_bronze_paw',
                'streak_days' => 7,
                'total_km' => 28.4,
                'total_walks' => 7,
                'bonus_xp' => 200,
            ],
            'likes_count' => 176,
            'reaction_counts' => [
                'bone' => 48,
                'fire' => 33,
                'paw' => 25,
                'heart' => 40,
                'clap' => 30,
            ],
            'comments_count' => 0,
            'created_at' => Carbon::now()->subHours(2),
        ]);

        if ($james) {
            Reaction::create([
                'user_id' => $james->id,
                'reactable_type' => Post::class,
                'reactable_id' => $post3->id,
                'reaction_type' => 'paw',
            ]);
        }

        // 5. Card 4: Omar's Park Photo / Agility Training Post
        $post4 = Post::create([
            'user_id' => $omar->id,
            'pet_id' => $pedro->id,
            'title' => 'Weekend agility training',
            'content' => 'Pedro mastered the obstacle course today! Hard work and treats always pay off 🐶⚡',
            'type' => 'general',
            'location' => 'Dhaka Agility Park',
            'visibility' => 'public',
            'likes_count' => 42,
            'reaction_counts' => [
                'bone' => 20,
                'fire' => 12,
                'paw' => 4,
                'heart' => 4,
                'clap' => 2,
            ],
            'comments_count' => 2,
            'created_at' => Carbon::now()->subHours(4),
        ]);

        if ($elena) {
            Reaction::create([
                'user_id' => $elena->id,
                'reactable_type' => Post::class,
                'reactable_id' => $post4->id,
                'reaction_type' => 'heart',
            ]);
        }

        PostComment::create([
            'post_id' => $post4->id,
            'user_id' => $sarah->id,
            'content' => "He is so fast and agile! Great form 🐕",
            'created_at' => Carbon::now()->subHours(3),
        ]);

        if ($david) {
            PostComment::create([
                'post_id' => $post4->id,
                'user_id' => $david->id,
                'content' => "What a champion! Pedro is getting stronger every week.",
                'created_at' => Carbon::now()->subHours(2)->subMinutes(30),
            ]);
        }

        // 6. Comment reaction (Omar heart on Elena's comment)
        Reaction::create([
            'user_id' => $omar->id,
            'reactable_type' => PostComment::class,
            'reactable_id' => $comment1->id,
            'reaction_type' => 'heart',
        ]);
    }
}
