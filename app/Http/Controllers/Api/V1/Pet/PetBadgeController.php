<?php

namespace App\Http\Controllers\Api\V1\Pet;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Services\Pet\PetBadgeService;
use Illuminate\Http\Request;

class PetBadgeController extends Controller
{
    protected PetBadgeService $badgeService;

    public function __construct(PetBadgeService $badgeService)
    {
        $this->badgeService = $badgeService;
    }

    /**
     * Get all earned and locked badges for a pet
     */
    public function index(Pet $pet)
    {
        $badges = $this->badgeService->getPetBadges($pet);

        return response_success('Pet badges retrieved successfully', $badges);
    }

    /**
     * Save up to 3 badges to display on pet profile
     */
    public function display(Request $request, Pet $pet)
    {
        if ($pet->user_id !== $request->user()->id) {
            return response_error('Unauthorized access to pet', [], 403);
        }

        $request->validate([
            'badge_ids' => 'present|array|max:3',
            'badge_ids.*' => 'string',
        ]);

        $result = $this->badgeService->updateDisplayedBadges($pet, $request->input('badge_ids', []));

        return response_success('Profile badges updated successfully', $result);
    }
}
