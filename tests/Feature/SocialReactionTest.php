<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Models\Reaction;
use App\Models\WalkSession;
use App\Http\Resources\Social\PostWalkSummaryResource;
use Tests\TestCase;

class SocialReactionTest extends TestCase
{
    public function test_user_can_react_with_different_reaction_types(): void
    {
        $user = User::factory()->create();
        $post = Post::create([
            'user_id' => $user->id,
            'type' => 'general',
            'content' => 'Test post for reactions',
        ]);

        // 1. React with paw
        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/social/posts/{$post->id}/react", [
            'reaction_type' => 'paw',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('reactions', $data);
        $this->assertArrayHasKey('paw', $data['reactions']['counts']);
        $this->assertTrue($data['reactions']['user_has_pawed']);

        // 2. React again with paw (should toggle off)
        $response2 = $this->actingAs($user, 'sanctum')->postJson("/api/v1/social/posts/{$post->id}/react", [
            'reaction_type' => 'paw',
        ]);
        $response2->assertStatus(200);
        $data2 = $response2->json('data');
        $this->assertFalse($data2['reactions']['user_has_pawed']);

        // 3. React with heart and fire
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/social/posts/{$post->id}/react", [
            'reaction_type' => 'heart',
        ])->assertStatus(200);

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/social/posts/{$post->id}/react", [
            'reaction_type' => 'fire',
        ])->assertStatus(200);

        $this->assertDatabaseHas('reactions', [
            'user_id' => $user->id,
            'reactable_type' => Post::class,
            'reactable_id' => $post->id,
            'reaction_type' => 'heart',
        ]);

        $this->assertDatabaseHas('reactions', [
            'user_id' => $user->id,
            'reactable_type' => Post::class,
            'reactable_id' => $post->id,
            'reaction_type' => 'fire',
        ]);
    }

    public function test_backward_compatible_like_endpoint_defaults_to_bone(): void
    {
        $user = User::factory()->create();
        $post = Post::create([
            'user_id' => $user->id,
            'type' => 'general',
            'content' => 'Test backward compatible like',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/social/posts/{$post->id}/like");
        $response->assertStatus(200);

        $this->assertDatabaseHas('reactions', [
            'user_id' => $user->id,
            'reactable_type' => Post::class,
            'reactable_id' => $post->id,
            'reaction_type' => 'bone',
        ]);
    }

    public function test_feed_includes_card_metadata_and_reactions(): void
    {
        $user = User::factory()->create();
        $post = Post::create([
            'user_id' => $user->id,
            'type' => 'achievement',
            'title' => 'Pedro Trail Runner',
            'content' => 'Badge unlocked',
            'metadata' => [
                'badge_name' => 'Trail Runner',
                'badge_level' => 'Level 2',
            ],
            'reaction_counts' => [
                'bone' => 5,
                'fire' => 2,
            ],
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/v1/social/posts");
        $response->assertStatus(200);

        $response->assertJsonFragment([
            'title' => 'Pedro Trail Runner',
            'badge_name' => 'Trail Runner',
        ]);
    }

    public function test_pace_calculation_in_post_walk_summary(): void
    {
        $walkSession = new WalkSession([
            'distance_km' => 4.2,
            'duration_seconds' => 3120, // 52 minutes
            'calories' => 280,
            'avg_speed_kmh' => 4.85,
        ]);

        $resource = (new PostWalkSummaryResource($walkSession))->resolve();

        $this->assertEquals('12:23 /km', $resource['pace']);
        $this->assertEquals('52 min', $resource['duration_human']);
    }
}

