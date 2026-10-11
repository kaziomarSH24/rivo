<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Controllers\Controller;
use App\Http\Resources\Chat\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\Request;

/**
 * @group Chat
 * @subgroup Conversations
 * List & create 1:1 or group conversations.
 */
class ConversationController extends Controller
{
    // List conversations for the authenticated user
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 20);
        $userId = $request->user()->id;

        $conversations = Conversation::query()
            ->whereHas('users', fn($q) => $q->where('user_id', $userId))
            ->with([
                'users:id,name,avatar',
                'latestMessage.user:id,name'
            ])
            ->withCount(['messages as unread_count' => function ($query) use ($userId) {
                $query->where('messages.user_id', '!=', $userId)
                      ->whereRaw('NOT EXISTS (
                    SELECT 1 FROM message_read_user
                    WHERE message_read_user.message_id = messages.id
                    AND message_read_user.user_id = ?
                )', [$userId]);
            }])
            ->latest('updated_at')
            ->paginate($perPage);

        return ConversationResource::collection($conversations);
    }

    // Create a new conversation or return existing one for 1-on-1 chats (Requires pet match/connection)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|size:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        $otherUserId = (int) $validated['user_ids'][0];

        if ($otherUserId === (int) $request->user()->id) {
            return response_error('You cannot start a conversation with yourself.', [], 400);
        }

        // Verify that the users have an accepted pet connection/match
        $currentUserPetIds = $request->user()->pets()->pluck('id');
        $otherUserPetIds = \App\Models\Pet::where('user_id', $otherUserId)->pluck('id');

        $connection = \App\Models\PetConnection::whereIn('pet_id', $currentUserPetIds)
            ->whereIn('connected_pet_id', $otherUserPetIds)
            ->where('status', 'accepted')
            ->first();

        if (!$connection) {
            return response_error('You can only start a conversation with matched pet owners.', [], 403);
        }

        $conversation = Conversation::findOrCreateDirect($request->user()->id, $otherUserId);

        // Ensure pet connection is linked with conversation_id if not already set
        if (!$connection->conversation_id) {
            $connection->update(['conversation_id' => $conversation->id]);
            \App\Models\PetConnection::where('pet_id', $connection->connected_pet_id)
                ->where('connected_pet_id', $connection->pet_id)
                ->where('status', 'accepted')
                ->update(['conversation_id' => $conversation->id]);
        }

        return response_success('Conversation retrieved successfully.', new ConversationResource($conversation->load('users')));
    }

}


