<?php

namespace App\Events\Chat\Group;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberRoleChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Conversation $conversation;
    public User $changedBy;
    public User $member;
    public string $newRole;

    public function __construct(Conversation $conversation, User $changedBy, User $member, string $newRole)
    {
        $this->conversation = $conversation;
        $this->changedBy = $changedBy;
        $this->member = $member;
        $this->newRole = $newRole;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversations.' . $this->conversation->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'group.member.role.changed';
    }

    public function broadcastWith(): array
    {
        $action = $this->newRole === 'admin' ? 'promoted' : 'demoted';
        $roleText = $this->newRole === 'admin' ? 'to admin' : 'to member';

        return [
            'message' => $this->changedBy->name . ' ' . $action . ' ' . $this->member->name . ' ' . $roleText . '.',
            'member_id' => $this->member->id,
            'new_role' => $this->newRole,
        ];
    }
}
