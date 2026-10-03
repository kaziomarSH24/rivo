<?php

namespace App\Http\Controllers\Api\V1\Pet;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLostPetReportRequest;
use App\Http\Resources\LostPetReportResource;
use App\Models\Pet;
use App\Services\Pet\LostPetReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Http\Requests\StoreCommunityPostRequest;
use App\Http\Resources\PostResource;
class LostPetReportController extends Controller
{
    protected LostPetReportService $lostPetReportService;

    public function __construct(LostPetReportService $lostPetReportService)
    {
        $this->lostPetReportService = $lostPetReportService;
    }

    /**
     * Get active lost report for a pet (Poster Details)
     */
    public function getActive(Pet $pet, Request $request)
    {
        Gate::authorize('view', $pet);

        $report = $pet->lostReports()->where('status', 'active')->first();

        if (!$report) {
            return response_error('No active lost report found for this pet.', [], 404);
        }

        return response_success('Active lost report retrieved successfully.', new LostPetReportResource($report));
    }

    /**
     * Store a newly created lost pet report (Toggle Lost Mode ON)
     */
    public function store(StoreLostPetReportRequest $request, Pet $pet)
    {
        Gate::authorize('update', $pet);

        try {
            $data = $request->validated();
            $data['user_id'] = $request->user()->id;
            
            $report = $this->lostPetReportService->createReport(
                $pet, 
                $data,
                $request
            );
            return response_success('Lost mode activated successfully. Poster created.', new LostPetReportResource($report), 201);
        } catch (\Exception $e) {
            return response_error($e->getMessage(), [], 400);
        }
    }

    /**
     * Mark pet as found (Toggle Lost Mode OFF)
     */
    /**
     * Publish the active lost report to the community social feed
     */
    public function postToCommunity(StoreCommunityPostRequest $request, Pet $pet)
    {
        Gate::authorize('update', $pet);

        try {
            $post = $this->lostPetReportService->postToCommunity($pet, $request->validated(), $request->user());
            $post->load('postable'); // Load the morph relation for the response
            return response_success('Lost pet poster published to community feed successfully.', new PostResource($post), 201);
        } catch (\Exception $e) {
            return response_error($e->getMessage(), [], 400);
        }
    }

    public function markAsFound(Pet $pet, Request $request)
    {
        Gate::authorize('update', $pet);

        try {
            $report = $this->lostPetReportService->markAsFound($pet);
            return response_success('Pet marked as found successfully.', new LostPetReportResource($report));
        } catch (\Exception $e) {
            return response_error($e->getMessage(), [], 400);
        }
    }

    /**
     * Community Feed: Get all active lost pets
     */
    public function communityFeed(Request $request)
    {
        $reports = $this->lostPetReportService->getAll(function ($query) use ($request) {
            $query->where('status', 'active');
            
            // Optionally, we could add distance filtering here if lat/lng are provided in the future
        });

        // Load relationships
        $reports->load(['pet', 'user']);

        return response_success('Community lost pets retrieved successfully.', 
            LostPetReportResource::collection($reports)->response()->getData(true)
        );
    }
}







