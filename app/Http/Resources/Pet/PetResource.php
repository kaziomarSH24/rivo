<?php

namespace App\Http\Resources\Pet;

use App\Http\Resources\UserResource;
use App\Models\PetConnection;
use App\Services\Pet\PetBadgeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentUserId = $request->user()?->id;
        $isOwner = $currentUserId && ($currentUserId === $this->user_id);

        $walkStat = $this->relationLoaded('walkStats') ? $this->walkStats : $this->walkStats()->first();
        $level = $walkStat ? (int) $walkStat->current_level : 1;
        $levelTitles = [
            0 => 'Novice Pup',
            1 => 'Park Explorer',
            2 => 'Trail Runner',
            3 => 'Adventure Hound',
            4 => 'Trail Master',
            5 => 'Legendary Tracker',
        ];
        $levelTitle = $levelTitles[$level] ?? "Level {$level} Explorer";

        $postsCount = $this->relationLoaded('posts') ? $this->posts->count() : $this->posts()->count();
        $friendsCount = PetConnection::where(function ($q) {
            $q->where('pet_id', $this->id)->orWhere('connected_pet_id', $this->id);
        })->where('status', 'accepted')->count();

        $achievements = app(PetBadgeService::class)->getProfileAchievements($this->resource);

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'is_owner' => $isOwner,
            'type' => $this->type,
            'name' => $this->name,
            'breed' => $this->breed,
            'age_years' => $this->age_years,
            'age_months' => $this->age_months,
            'weight' => (float) $this->weight,
            'bio' => $this->bio,
            'traits' => $this->traits,
            'personality' => $this->personality,
            'energy_level' => $this->energy_level,
            'training_level' => $this->training_level,
            'is_vaccinated' => (bool) $this->is_vaccinated,
            'gender' => $this->gender,
            'photo' => $this->photo ? url('storage/' . $this->photo) : null,
            'level' => $level,
            'level_title' => $levelTitle,
            'posts_count' => $postsCount,
            'friends_count' => $friendsCount,
            'achievements' => $achievements,
            'compatibility' => $this->when($request->route('pet') instanceof \App\Models\Pet && $request->route('pet')->id !== $this->id, function() use ($request) { return $this->calculateCompatibilityWith($request->route('pet')); }),
            'distance' => $this->when(isset($this->distance), function() { return round((float) $this->distance, 1); }),
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
