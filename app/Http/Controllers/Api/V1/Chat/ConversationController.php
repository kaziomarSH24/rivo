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
                'users:id,name',
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

    // Create a new conversation or return existing one for 1-on-1 chats
     public function store(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'name' => 'nullable|string|max:255',
        ]);

        $userIds = array_unique(array_merge($validated['user_ids'], [$request->user()->id]));
        sort($userIds);

        // If group chat (more than 2 users), always create a new conversation
        if (count($userIds) > 2) {
            $conversation = Conversation::create([
                'name' => $validated['name'],
                'created_by' => $request->user()->id,
            ]);
            $conversation->users()->sync($userIds);
            $conversation->users()->updateExistingPivot($request->user()->id, ['role' => 'admin']);
            return new ConversationResource($conversation->load('users'));
        }

        // if 1-on-1 chat, check if conversation already exists
        $existingConversation = $request->user()->conversations()
            ->whereHas('users', function ($query) use ($userIds) {
                $query->whereIn('user_id', $userIds);
            }, '=', count($userIds))
            ->whereHas('users', null, '=', count($userIds))
            ->whereNull('name')
            ->first();

        if ($existingConversation) {
            return response_success('Conversation already exists.', new ConversationResource($existingConversation->load('users')));
        }

        $conversation = Conversation::create(['created_by' => $request->user()->id]);
        $conversation->users()->sync($userIds);

        return response_success('Conversation created successfully.', new ConversationResource($conversation->load('users')), 201);
    }

}
