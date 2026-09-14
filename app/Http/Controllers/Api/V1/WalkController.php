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

        return response_success('Walk history retrieved', WalkSessionResource::collection($walks)->response()->getData(true));
    }

    /**
     * Complete a new walk
     */
    public function store(StoreWalkSessionRequest $request, Pet $pet)
    {
        Gate::authorize('view', $pet);

        $result = $this->walkService->endWalk($pet->id, $request->validated());

        return response_success('Walk completed successfully', [
            'walk' => new WalkSessionResource($result['walk']),
            'level_up' => $result['level_up'],
            'bonus_xp' => $result['bonus_xp'],
            'new_reward' => $result['new_reward'],
        ], 201);
    }

    /**
     * Get single walk details
     */
    public function show(WalkSession $walk, Request $request)
    {
        $walk->load(['pet']);
        Gate::authorize('view', $walk->pet);

        return response_success('Walk details retrieved', new WalkSessionResource($walk));
    }

    /**
     * Get Pet Walk Statistics & Heatmap
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
     * Get current goals and stats
     */
    public function getStats(Pet $pet, Request $request)
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
     * Get Rewards Status
     */
    public function rewards(Pet $pet, Request $request)
    {
        Gate::authorize('view', $pet);

        $rewards = WalkReward::orderBy('level')->get();
        $stat = $pet->walkStats()->first();
        $currentLevel = $stat ? $stat->current_level : 0;

        $data = $rewards->map(function ($r) use ($currentLevel) {
            return [
                'level' => $r->level,
                'required_streak_days' => $r->required_streak_days,
                'bonus_xp' => $r->bonus_xp,
                'reward_title' => $r->reward_title,
                'is_unlocked' => $currentLevel >= $r->level
            ];
        });

        return response_success('Rewards retrieved', $data);
    }
}



