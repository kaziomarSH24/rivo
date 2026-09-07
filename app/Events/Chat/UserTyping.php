<?php

namespace App\Events\Chat;

use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class UserTyping implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public User $user;
    public int $conversationId;
    public function __construct(User $user, int $conversationId)
    {
        $this->user = $user;
        $this->conversationId = $conversationId;
    }
    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversations.' . $this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'user.typing';
    }
}
