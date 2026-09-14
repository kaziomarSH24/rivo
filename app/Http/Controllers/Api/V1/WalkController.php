<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Walk\StoreWalkSessionRequest;
use App\Http\Resources\WalkSessionResource;
use App\Http\Resources\PetWalkStatResource;
use App\Http\Resources\WalkRewardResource;
use App\Models\Pet;
use App\Models\WalkSession;
use App\Models\WalkReward;
use App\Services\WalkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WalkController extends Controller
{
    protected WalkService $walkService;

    public function __construct(WalkService $walkService)
    {
        $this->walkService = $walkService;
    }

    /**
     * List walk history for a pet
     */
    public function index(Pet $pet, Request $request)
    {
        Gate::authorize('view', $pet);

        $walks = $this->walkService->getAll(function ($query) use ($pet) {
            $query->where('pet_id', $pet->id);
        });

        return WalkSessionResource::collection($walks);
    }

    /**
     * Complete a walk session
     */
    public function store(StoreWalkSessionRequest $request, Pet $pet)
    {
        Gate::authorize('view', $pet);

        $data = $request->validated();
        $result = $this->walkService->endWalk($pet->id, $data);

        return response_success('Walk completed successfully', [
            'walk' => new WalkSessionResource($result['walk']),
            'level_up' => $result['level_up'],
            'streak_updated' => $result['streak_updated'],
            'bonus_xp' => $result['bonus_xp'],
        ], 201);
    }

    /**
     * Show a specific walk session
     */
    public function show(Pet $pet, WalkSession $walk)
    {
        Gate::authorize('view', $pet);
        
        if ($walk->pet_id !== $pet->id) {
            return response_error('Walk session does not belong to this pet', 403);
        }

        $walk->load(['pet', 'route']);

        return response_success('Walk session retrieved', new WalkSessionResource($walk));
    }

    /**
     * Get Pet Walk Statistics
     */
    public function statistics(Pet $pet, Request $request)
    {
        Gate::authorize('view', $pet);

        $stats = $this->walkService->getPetStatistics($pet->id);
        
        // Heatmap coordinates (last 10 walks as an example)
        $heatmapWalks = WalkSession::where('pet_id', $pet->id)
            ->whereNotNull('route_coordinates')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
            
        $heatmap = $heatmapWalks->pluck('route_coordinates')->flatten(1);

        return response_success('Statistics retrieved', [
            'statistics' => $stats,
            'heatmap_coordinates' => $heatmap
        ]);
    }

    /**
     * Get Pet Walk Goals and Streak
     */
    public function stats(Pet $pet)
    {
        Gate::authorize('view', $pet);

        $stat = $pet->walkStats()->firstOrCreate(
            ['pet_id' => $pet->id],
            [
                'daily_distance_goal_km' => 2.50,
                'total_xp' => 0,
                'current_level' => 0,
                'current_streak_days' => 0,
                'longest_streak_days' => 0,
            ]
        );

        return response_success('Stats retrieved', new PetWalkStatResource($stat));
    }

    /**
     * Get unlocked and locked rewards for a pet
     */
    public function rewards(Pet $pet)
    {
        Gate::authorize('view', $pet);

        $stat = $pet->walkStats()->first();
        $currentStreak = $stat ? $stat->current_streak_days : 0;

        $rewards = WalkReward::orderBy('required_streak_days', 'asc')->get();
        
        $formattedRewards = $rewards->map(function ($reward) use ($currentStreak) {
            $resource = new WalkRewardResource($reward);
            $resource->setCurrentStreak($currentStreak);
            return $resource;
        });

        return response_success('Rewards retrieved', $formattedRewards);
    }

    public function routesTab(Pet $pet, Request $request)
    {
        Gate::authorize('view', $pet);
        
        $insights = $this->walkService->getRoutesTabInsights($pet->id, $request->user()->id);
        
        return response_success('Routes tab data retrieved', $insights);
    }
}
