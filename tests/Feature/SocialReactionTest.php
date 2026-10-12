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
        $this->assertEquals('reacted', $data['status']);
        $this->assertEquals('paw', $data['user_reaction']);
        $this->assertTrue($data['is_liked']);
        $this->assertEquals(1, $data['reactions']['paw']);
        $this->assertEquals(1, $data['likes_count']);

        // 2. React again with paw (should toggle off / unreact)
        $response2 = $this->actingAs($user, 'sanctum')->postJson("/api/v1/social/posts/{$post->id}/react", [
            'reaction_type' => 'paw',
        ]);
        $response2->assertStatus(200);
        $data2 = $response2->json('data');
        $this->assertEquals('unreacted', $data2['status']);
        $this->assertNull($data2['user_reaction']);
        $this->assertFalse($data2['is_liked']);
        $this->assertEquals(0, $data2['reactions']['paw']);
        $this->assertEquals(0, $data2['likes_count']);

        // 3. React with heart
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/social/posts/{$post->id}/react", [
            'reaction_type' => 'heart',
        ])->assertStatus(200);

        // 4. Switch reaction to fire (Facebook model: replaces heart with fire)
        $responseSwitch = $this->actingAs($user, 'sanctum')->postJson("/api/v1/social/posts/{$post->id}/react", [
            'reaction_type' => 'fire',
        ]);
        $responseSwitch->assertStatus(200);
        $dataSwitch = $responseSwitch->json('data');
        $this->assertEquals('updated', $dataSwitch['status']);
        $this->assertEquals('fire', $dataSwitch['user_reaction']);
        $this->assertEquals(1, $dataSwitch['reactions']['fire']);
        $this->assertEquals(0, $dataSwitch['reactions']['heart']);
        $this->assertEquals(1, $dataSwitch['likes_count']);

        $this->assertDatabaseMissing('reactions', [
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

        // 5. Verify show endpoint returns user_reaction and is_liked
        $showResponse = $this->actingAs($user, 'sanctum')->getJson("/api/v1/social/posts/{$post->id}");
        $showResponse->assertStatus(200);
        $showData = $showResponse->json('data');
        $this->assertEquals('fire', $showData['user_reaction']);
        $this->assertTrue($showData['is_liked']);
        $this->assertEquals(1, $showData['reactions']['fire']);
        $this->assertEquals(1, $showData['likes_count']);
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

