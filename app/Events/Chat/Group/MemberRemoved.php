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

class MemberRemoved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Conversation $conversation;
    public User $removedBy;
    public User $member;

    public function __construct(Conversation $conversation, User $removedBy, User $member)
    {
        $this->conversation = $conversation;
        $this->removedBy = $removedBy;
        $this->member = $member;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversations.' . $this->conversation->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'group.member.removed';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->removedBy->name . ' removed ' . $this->member->name . ' from the group.',
            'removed_member_id' => $this->member->id,
        ];
    }
}
