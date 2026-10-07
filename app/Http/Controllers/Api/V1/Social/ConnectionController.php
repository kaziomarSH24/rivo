<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Services\Social\ConnectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Http\Resources\Social\PetConnectionResource;

class ConnectionController extends Controller
{
    protected $connectionService;

    public function __construct(ConnectionService $connectionService)
    {
        $this->connectionService = $connectionService;
    }

    /**
     * Get connections (friends & matches) for a pet
     */
    public function index(Request $request, Pet $pet)
    {
        Gate::authorize('view', $pet);

        $connections = $this->connectionService->getAll(function ($query) use ($pet) {
            $query->where('pet_id', $pet->id)
                ->where('status', 'accepted');
        });

        $resource = \App\Http\Resources\Social\PetConnectionResource::collection($connections);
        return response_success('Connections retrieved', [
            'data' => $resource->items(),
            'pagination' => [
                'current_page' => $connections->currentPage(),
                'last_page' => $connections->lastPage(),
                'total' => $connections->total(),
            ]
        ]);
    }

    /**
     * Get pending friend requests
     */
    public function requests(Request $request, Pet $pet)
    {
        Gate::authorize('view', $pet);

        $requests = $this->connectionService->getPendingRequests($pet);

        $resource = \App\Http\Resources\Social\PetConnectionResource::collection($requests);
        return response_success('Pending requests retrieved', [
            'data' => $resource->items(),
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'total' => $requests->total(),
            ]
        ]);
    }

    /**
     * Send a direct friend request
     */
    public function sendRequest(Request $request, Pet $pet)
    {
        $request->validate([
            'target_pet_id' => 'required|exists:pets,id',
        ]);
        Gate::authorize('view', $pet);

        if ($pet->id == $request->target_pet_id) {
            return response_error('Cannot connect with your own pet', [], 400);
        }

        try {
            $targetPet = Pet::findOrFail($request->target_pet_id);
            $connection = $this->connectionService->sendRequest($pet, $targetPet);
            return response_success('Friend request sent', $connection);
        } catch (\Exception $e) {
            return response_error($e->getMessage(), [], 400);
        }
    }

    /**
     * Accept or decline a friend request
     */
    public function respond(Request $request, Pet $pet)
    {
        $request->validate([
            'requester_pet_id' => 'required|exists:pets,id',
            'action' => 'required|in:accepted,declined'
        ]);
        Gate::authorize('view', $pet);

        try {
            $requesterPet = Pet::findOrFail($request->requester_pet_id);
            $connection = $this->connectionService->respondToRequest($pet, $requesterPet, $request->action);
            return response_success('Request ' . $request->action, $connection);
        } catch (\Exception $e) {
            return response_error('Request not found or already processed', [], 400);
        }
    }
}



