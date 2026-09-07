<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ConversationPolicy
{
    /**
     * only user accessing the conversation can view it.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->users->contains($user);
    }

    /**
     * determine whether the user can add members to the group.
     */
    public function addMember(User $user, Conversation $conversation): bool
    {
        // only group admins can add members
        return $conversation->isAdmin($user);
    }

    /**
     * determine whether the user can remove members from the group.
     */
    public function removeMember(User $user, Conversation $conversation): bool
    {
        // only group admins can remove members
        return $conversation->isAdmin($user);
    }

    /**
     * determine whether the user can make another user an admin.
     */
    public function makeAdmin(User $user, Conversation $conversation): bool
    {
        // only group admins can make another user an admin
        return $conversation->isAdmin($user);
    }

     /**
        * determine whether the user can demote an admin to member.
    */
      public function demoteAdmin(User $user, Conversation $conversation): bool
    {
        // only group admins can demote another admin
        return $conversation->isAdmin($user);
    }

    /**
     * determine who can leave the group.
     */
    public function leaveGroup(User $user, Conversation $conversation): bool
    {
        // without the group creator, any member can leave the group
        return $conversation->created_by !== $user->id && $conversation->users->contains($user);
    }
}

