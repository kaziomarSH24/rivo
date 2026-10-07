<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Pet;

class FriendRequestNotification extends Notification
{
    use Queueable;

    protected $requesterPet;
    protected $targetPet;

    public function __construct(Pet $requesterPet, Pet $targetPet)
    {
        $this->requesterPet = $requesterPet;
        $this->targetPet = $targetPet;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'friend_request',
            'title' => 'New Friend Request',
            'message' => "{->requesterPet->name} sent a friend request to {->targetPet->name}!",
            'requester_pet_id' => $this->requesterPet->id,
            'requester_pet_name' => $this->requesterPet->name,
            'requester_pet_photo' => $this->requesterPet->photo,
            'target_pet_id' => $this->targetPet->id,
        ];
    }
}
