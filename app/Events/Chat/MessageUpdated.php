<?php

namespace App\Events\Chat;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class MessageUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public Message $message;
    public function __construct(Message $message)
    {
        $this->message = $message;
    }
    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversations.' . $this->message->conversation_id)];
    }
    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }

    public function broadcastAs(): string
    {
        return 'message.updated';
    }
}
