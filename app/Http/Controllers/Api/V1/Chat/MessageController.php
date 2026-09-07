<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Events\Chat\MessageDeleted;
use App\Events\Chat\MessageSent;
use App\Events\Chat\MessagesRead;
use App\Events\Chat\MessageUpdated;
use App\Events\Chat\UserTyping;
use App\Http\Controllers\Controller;
use App\Http\Resources\Chat\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Traits\FileUploadTrait;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Chat
 * @subgroup Messages
 * Conversation message retrieval, creation, editing, deletion, typing indicators and read receipts.
 */
class MessageController extends Controller
{
    use AuthorizesRequests, FileUploadTrait;

    // Show messages in a conversation
    public function index(Request $request, Conversation $conversation)
    {
        $perPage = $request->query('per_page', 50);
        $this->authorize('view', $conversation);

        $messages = $conversation->messages()
            ->with('user')
            ->withExists(['readBy as is_read' => function ($query) {
                $query->whereColumn('message_read_user.user_id', '!=', 'messages.user_id');
            }])
            ->latest()
            ->paginate($perPage);

        return MessageResource::collection($messages);
    }

    // Send a message in a conversation
    public function store(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'body' => 'required_without:media|nullable|string',
            'media' => 'required_without:body|nullable|file|mimes:jpg,jpeg,png,webp,mp4,mp3,pdf|max:10240',
            'parent_id' => 'nullable|exists:messages,id',
        ]);
        $conversation = Conversation::findOrFail($validated['conversation_id']);
        $this->authorize('view', $conversation);

        $mediaPath = null;
        $mediaType = null;
        if ($request->hasFile('media')) {
            $mediaPath = $this->handleFileUpload($request, 'media', 'chat_media');
            $mediaType = explode('/', $request->file('media')->getMimeType())[0];
        }

        $message = $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'] ?? null,
            'media_url' => $mediaPath,
            'media_type' => $mediaType,
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        broadcast(new MessageSent($message))->toOthers();
        return response_success('Message sent.', $message, 201);
    }

    /**
     * update a message
     */
    public function update(Request $request, Message $message)
    {
        $this->authorize('update', $message);
        $validated = $request->validate(['body' => 'required|string']);

        $message->update([
            'body' => $validated['body'],
            'edited_at' => now(),
        ]);

        broadcast(new MessageUpdated($message))->toOthers();
        return response_success('Message updated.', new MessageResource($message));
    }

    /**
     * delete a message (soft delete)
     */
    public function destroy(Message $message)
    {
        $this->authorize('delete', $message);
        $message->delete();
        broadcast(new MessageDeleted($message->id, $message->conversation_id))->toOthers();
        return response_success('Message deleted.');
    }

    /**
     * broadcast typing indicator
     */
    public function typing(Request $request)
    {
        $validated = $request->validate(['conversation_id' => 'required|exists:conversations,id']);
        $conversation = Conversation::findOrFail($validated['conversation_id']);
        $this->authorize('view', $conversation);

        broadcast(new UserTyping($request->user(), $conversation->id))->toOthers();
        return response()->noContent();
    }

    public function markAsRead(Request $request, Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        // Get all message IDs in the conversation
        $messageIds = $conversation->messages()->pluck('id');
        $userId = $request->user()->id;

        // Find messages that are not yet marked as read by this user
        $unreadMessageIds = DB::table('messages')
            ->whereIn('id', $messageIds)
            ->whereRaw('NOT EXISTS (
                SELECT 1 FROM message_read_user
                WHERE message_read_user.message_id = messages.id
                AND message_read_user.user_id = ?
            )', [$userId])
            ->pluck('id');

        if ($unreadMessageIds->isNotEmpty()) {
            $dataToInsert = $unreadMessageIds->map(function ($messageId) use ($userId) {
                return [
                    'message_id' => $messageId,
                    'user_id' => $userId,
                    'read_at' => now(),
                ];
            })->all();

            DB::table('message_read_user')->insert($dataToInsert);

            // broadcast read event
            broadcast(new MessagesRead($conversation->id, $request->user()))->toOthers();
        }

        return response_success('Messages marked as read.');
    }
}
