<?php

namespace App\Services\Social;

use App\Models\PetSwipe;
use App\Models\Pet;
use App\Models\PetConnection;
use App\Notifications\NewLikeNotification;
use App\Notifications\NewMatchNotification;
use App\Services\BaseService;
use Closure;

class MatchService extends BaseService
{
    protected string $modelClass = PetSwipe::class;

    protected function getAllowedFilters(): array
    {
        return ['action'];
    }

    protected function getAllowedIncludes(): array
    {
        return ['pet', 'targetPet'];
    }

    protected function getAllowedSorts(): array
    {
        return ['created_at'];
    }

    /**
     * Get pets available for swiping
     */
    public function discoverPets(Pet $currentPet, array $filters, $userLat = null, $userLon = null)
    {
        // 1. Get IDs of pets we already swiped on
        $swipedIds = PetSwipe::where('pet_id', $currentPet->id)->pluck('target_pet_id')->toArray();

        // 2. Get IDs of connected pets
        $connectedIds = PetConnection::where('pet_id', $currentPet->id)
            ->whereIn('status', ['accepted', 'pending'])
            ->pluck('connected_pet_id')
            ->toArray();

        // Also exclude pets belonging to the same user
        $sameUserPets = Pet::where('user_id', $currentPet->user_id)->pluck('id')->toArray();

        $excludedIds = array_unique(array_merge($swipedIds, $connectedIds, $sameUserPets));

        // 3. Base Query
        $query = Pet::whereNotIn('pets.id', $excludedIds)
            ->where('is_lost', false)
            ->with('user'); // Load user for frontend distance calculation if needed

        // Filter by age
        if (!empty($filters['age'])) {
            if ($filters['age'] === 'puppy') {
                $query->where('age_years', 0);
            } elseif ($filters['age'] === 'adult') {
                $query->whereBetween('age_years', [1, 7]);
            } elseif ($filters['age'] === 'senior') {
                $query->where('age_years', '>', 7);
            }
        }

        // Filter by size
        if (!empty($filters['size'])) {
            if ($filters['size'] === 'small') {
                $query->where('weight', '<', 10);
            } elseif ($filters['size'] === 'medium') {
                $query->whereBetween('weight', [10, 25]);
            } elseif ($filters['size'] === 'large') {
                $query->where('weight', '>', 25);
            }
        }

        // Filter by breed
        if (!empty($filters['breed']) && strtolower($filters['breed']) !== 'any breed') {
            $query->where('breed', 'LIKE', '%' . $filters['breed'] . '%');
        }

        // Filter by gender
        if (!empty($filters['gender']) && strtolower($filters['gender']) !== 'any') {
            $query->where('gender', strtolower($filters['gender']));
        }

        // Filter by vaccination
        if (isset($filters['vaccinated'])) {
            if ($filters['vaccinated'] === 'required') {
                $query->where('is_vaccinated', true);
            } elseif ($filters['vaccinated'] === 'not required') {
                // Do nothing, show all
            }
        }

        // Apply distance filter if coordinates available
        if ($userLat !== null && $userLon !== null && !empty($filters['distance'])) {
            $maxDistance = (int)$filters['distance'];
            
            $query->join('users', 'pets.user_id', '=', 'users.id')
                  ->select('pets.*')
                  ->selectRaw(
                      "( 6371 * acos( cos( radians(?) ) * cos( radians( users.latitude ) ) * cos( radians( users.longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( users.latitude ) ) ) ) AS distance",
                      [$userLat, $userLon, $userLat]
                  )
                  ->having('distance', '<=', $maxDistance)
                  ->orderBy('distance');
        } else {
            $query->inRandomOrder();
        }

        return $query->paginate(20);
    }

    /**
     * Process a swipe action (like or pass)
     */
    public function processSwipe(Pet $currentPet, Pet $targetPet, string $action)
    {
        // Save the swipe
        $swipe = PetSwipe::updateOrCreate(
            ['pet_id' => $currentPet->id, 'target_pet_id' => $targetPet->id],
            ['action' => $action]
        );

        if ($action === 'liked' && $swipe->wasRecentlyCreated) {
            $targetPet->user->notify(new NewLikeNotification($currentPet, $targetPet));
        }

        $isMatch = false;

        if ($action === 'liked') {
            // Check if the other pet also liked us
            $mutualSwipe = PetSwipe::where('pet_id', $targetPet->id)
                ->where('target_pet_id', $currentPet->id)
                ->where('action', 'liked')
                ->exists();

            if ($mutualSwipe) {
                $isMatch = true;

                // Create connections for both
                PetConnection::firstOrCreate(
                    ['pet_id' => $currentPet->id, 'connected_pet_id' => $targetPet->id],
                    ['status' => 'accepted', 'source' => 'match']
                );
                
                PetConnection::firstOrCreate(
                    ['pet_id' => $targetPet->id, 'connected_pet_id' => $currentPet->id],
                    ['status' => 'accepted', 'source' => 'match']
                );

                // Notify both users about the match
                $currentPet->user->notify(new NewMatchNotification($targetPet, $currentPet));
                $targetPet->user->notify(new NewMatchNotification($currentPet, $targetPet));
            }
        }

        return [
            'swipe' => $swipe,
            'is_match' => $isMatch
        ];
    }
}

