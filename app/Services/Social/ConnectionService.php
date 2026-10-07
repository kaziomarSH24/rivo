<?php

namespace App\Services\Social;

use App\Models\PetConnection;
use App\Models\Pet;
use App\Notifications\FriendRequestNotification;
use App\Notifications\FriendRequestAcceptedNotification;
use App\Services\BaseService;

class ConnectionService extends BaseService
{
    protected string $modelClass = PetConnection::class;

    protected function getAllowedFilters(): array
    {
        return ['status', 'source'];
    }

    protected function getAllowedIncludes(): array
    {
        return ['pet', 'connectedPet'];
    }

    protected function getAllowedSorts(): array
    {
        return ['created_at'];
    }

    /**
     * Send a friend request
     */
    public function sendRequest(Pet $currentPet, Pet $targetPet)
    {
        // Check if already connected or pending
        $existing = PetConnection::where('pet_id', $currentPet->id)
            ->where('connected_pet_id', $targetPet->id)
            ->first();

        if ($existing) {
            throw new \Exception('Connection already exists or is pending.');
        }

        // Check if target already sent a request to current
        $reverse = PetConnection::where('pet_id', $targetPet->id)
            ->where('connected_pet_id', $currentPet->id)
            ->where('status', 'pending')
            ->first();

        if ($reverse) {
            // Auto accept if reverse exists
            $reverse->update(['status' => 'accepted']);
            $conn = PetConnection::create([
                'pet_id' => $currentPet->id,
                'connected_pet_id' => $targetPet->id,
                'status' => 'accepted',
                'source' => 'direct_request'
            ]);
        $targetPet->user->notify(new FriendRequestNotification($currentPet, $targetPet));
        return $conn;
        }

        $conn = PetConnection::create([
            'pet_id' => $currentPet->id,
            'connected_pet_id' => $targetPet->id,
            'status' => 'pending',
            'source' => 'direct_request'
        ]);
        $targetPet->user->notify(new FriendRequestNotification($currentPet, $targetPet));
        return $conn;
    }

    /**
     * Respond to a friend request
     */
    public function respondToRequest(Pet $currentPet, Pet $requesterPet, string $action)
    {
        $request = PetConnection::where('pet_id', $requesterPet->id)
            ->where('connected_pet_id', $currentPet->id)
            ->where('status', 'pending')
            ->firstOrFail();

        if ($action === 'accepted') {
            $request->update(['status' => 'accepted']);
            
            // Create the reverse connection for easy querying
            PetConnection::firstOrCreate([
                'pet_id' => $currentPet->id,
                'connected_pet_id' => $requesterPet->id,
            ], [
                'status' => 'accepted',
                'source' => 'direct_request'
            ]);

            $requesterPet->user->notify(new FriendRequestAcceptedNotification($currentPet, $requesterPet));

            return $request;
        }

        // Declined
        $request->update(['status' => 'declined']);
        return $request;
    }

    /**
     * Get pending requests for the current pet
     */
    public function getPendingRequests(Pet $currentPet)
    {
        return PetConnection::with('pet') // The one who sent the request
            ->where('connected_pet_id', $currentPet->id)
            ->where('status', 'pending')
            ->paginate(20);
    }
}


