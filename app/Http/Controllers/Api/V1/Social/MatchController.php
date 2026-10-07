<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Http\Resources\Pet\PetResource;
use App\Services\Social\MatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MatchController extends Controller
{
    protected $matchService;

    public function __construct(MatchService $matchService)
    {
        $this->matchService = $matchService;
    }

    /**
     * Discover potential matches nearby
     */
    public function discover(Request $request, Pet $pet)
    {
        // Authorize pet belongs to user
        Gate::authorize('view', $pet);

        $filters = $request->only(['age', 'size', 'gender', 'distance']);

        $pets = $this->matchService->discoverPets(
            $pet,
            $filters,
            $request->user()->latitude,
            $request->user()->longitude
        );

        return response_success('Potential matches retrieved', $pets);
    }

    /**
     * Swipe on a pet (like or pass)
     */
    public function swipe(Request $request, Pet $pet)
    {
        $request->validate([
            'target_pet_id' => 'required|exists:pets,id',
            'action' => 'required|in:liked,passed'
        ]);
        Gate::authorize('view', $pet);

        if ($pet->id == $request->target_pet_id) {
            return response_error('Cannot swipe on your own pet', [], 400);
        }

        $targetPet = Pet::findOrFail($request->target_pet_id);

        $result = $this->matchService->processSwipe($pet, $targetPet, $request->action);

        return response_success('Swipe processed', $result);
    }
}


