<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Events\Chat\Group\MemberAdded;
use App\Events\Chat\Group\MemberRemoved;
use App\Events\Chat\Group\MemberRoleChanged;
use App\Http\Controllers\Controller;
use App\Http\Resources\Chat\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * @group Chat
 * @subgroup Groups
 * Manage group membership & roles.
 */
class GroupController extends Controller
{
    use AuthorizesRequests;

    /**
     * add group members
     */
    public function addMember(Request $request, Conversation $conversation)
    {
        $this->authorize('addMember', $conversation);

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $conversation->users()->syncWithoutDetaching($validated['user_ids']);

        // Broadcast event to notify existing members about the new members
        $addedMembers = User::whereIn('id', $validated['user_ids'])->get();
        broadcast(new MemberAdded($conversation, $request->user(), $addedMembers->all()))->toOthers();

        return response_success('Member(s) added successfully.', new ConversationResource($conversation->load('users')));
    }

    /**
     * remove group member
     */
    public function removeMember(Request $request, Conversation $conversation)
    {
        $this->authorize('removeMember', $conversation);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);
        $member = User::findOrFail($validated['user_id']);

        if ($conversation->created_by == $validated['user_id']) {
            return response_error('Cannot remove the group creator.');
        }

        $conversation->users()->detach($validated['user_id']);

         broadcast(new MemberRemoved($conversation, $request->user(), $member))->toOthers();

        return response_success('Member removed successfully.', new ConversationResource($conversation->load('users')));
    }

    /**
     * leave group
     */
    public function leaveGroup(Conversation $conversation)
    {
        $this->authorize('leaveGroup', $conversation);
        $user = auth()->user();

        if ($conversation->created_by == $user->id) {
            return response_error('Group creator cannot leave. You must delete the group instead.');
        }

        $conversation->users()->detach($user->id);

        return response_success('You have left the group.');
    }

    /**
     * promote a member to admin
     */
    public function promoteToAdmin(Request $request, Conversation $conversation)
    {
        $this->authorize('makeAdmin', $conversation);

        $validated = $request->validate(['user_id' => 'required|exists:users,id']);

        if (!$conversation->users->contains($validated['user_id'])) {
            return response_error('User is not a member of this group.');
        }

        $member = User::findOrFail($validated['user_id']);
        $conversation->users()->updateExistingPivot($validated['user_id'], ['role' => 'admin']);

        broadcast(new MemberRoleChanged($conversation, $request->user(), $member, 'admin'))->toOthers();

        return response_success('User promoted to admin successfully.');
    }

    /**
     * demote an admin to member
     */
    public function demoteToMember(Request $request, Conversation $conversation)
    {
        $this->authorize('makeAdmin', $conversation);

        $validated = $request->validate(['user_id' => 'required|exists:users,id']);
        $member = User::findOrFail($validated['user_id']);
        if ($conversation->created_by == $validated['user_id']) {
            return response_error('Cannot demote the group creator.');
        }

        $conversation->users()->updateExistingPivot($validated['user_id'], ['role' => 'member']);

        broadcast(new MemberRoleChanged($conversation, $request->user(), $member, 'member'))->toOthers();

        return response_success('Admin demoted to member successfully.');
    }
}

