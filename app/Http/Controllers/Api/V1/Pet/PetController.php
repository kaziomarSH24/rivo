<?php

namespace App\Http\Controllers\Api\V1\Pet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pet\StorePetRequest;
use App\Http\Requests\Pet\UpdatePetRequest;
use App\Http\Resources\Pet\PetResource;
use App\Models\Pet;
use App\Services\Pet\PetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @group Pet Management
 * Endpoints for managing user pets.
 */
class PetController extends Controller
{
    protected PetService $petService;

    public function __construct(PetService $petService)
    {
        $this->petService = $petService;
    }

    /**
     * List user's pets
     */
    public function index(Request $request)
    {
        // Force the query to only return the authenticated user's pets
        $pets = $this->petService->getAll(function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        });

        return response_success('Pets retrieved successfully', PetResource::collection($pets)->response()->getData(true));
    }

    /**
     * Store a new pet
     */
    public function store(StorePetRequest $request)
    {
        $user = $request->user();
        $petCount = $user->pets()->count();
        $planId = $user->plan_id ?: 1; // Default to free plan if null
        
        $maxPets = match ($planId) {
            1 => 1,
            2 => 2,
            3 => 3,
            default => 1,
        };

        if ($petCount >= $maxPets) {
            return response_error(
                "You have reached the maximum number of pets ({$maxPets}) allowed for your current plan. Please upgrade to add more pets.", 
                [], 
                403
            );
        }

        $pet = $this->petService->createPet($request->validated(), $user, $request);
        return response_success('Pet added successfully', new PetResource($pet), 201);
    }

    /**
     * Show pet details
     */
    public function show(Pet $pet, Request $request)
    {
        if ($pet->user_id !== $request->user()->id) {
            return response_error('Unauthorized access to pet', [], 403);
        }

        return response_success('Pet retrieved successfully', new PetResource($pet));
    }

    /**
     * Update pet details
     */
    public function update(UpdatePetRequest $request, Pet $pet)
    {
        if ($pet->user_id !== $request->user()->id) {
            return response_error('Unauthorized access to pet', [], 403);
        }

        $updatedPet = $this->petService->updatePet($pet, $request->validated(), $request);
        return response_success('Pet updated successfully', new PetResource($updatedPet));
    }

    /**
     * Delete a pet
     */
    public function destroy(Pet $pet, Request $request)
    {
        if ($pet->user_id !== $request->user()->id) {
            return response_error('Unauthorized access to pet', [], 403);
        }

        $this->petService->deletePet($pet);
        return response_success('Pet deleted successfully');
    }
}







