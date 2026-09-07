<?php

namespace App\Events\Chat\Group;

use App\Http\Resources\Chat\UserResource;
use App\Http\Resources\UserResource as ResourcesUserResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Conversation $conversation;
    public User $addedBy;
    public array $members;

    /**
     * Create a new event instance.
     */
    public function __construct(Conversation $conversation, User $addedBy, array $members)
    {
        $this->conversation = $conversation;
        $this->addedBy = $addedBy;
        $this->members = $members;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversations.' . $this->conversation->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'group.member.added';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'message' => $this->addedBy->name . ' added ' . implode(', ', array_map(fn($user) => $user->name, $this->members)) . ' to the group.',
            'added_members' => ResourcesUserResource::collection($this->members),
        ];
    }
}
