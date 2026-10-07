<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Pet;

class FriendRequestAcceptedNotification extends Notification
{
    use Queueable;

    protected $accepterPet;
    protected $requesterPet;

    public function __construct(Pet $accepterPet, Pet $requesterPet)
    {
        $this->accepterPet = $accepterPet;
        $this->requesterPet = $requesterPet;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'friend_request_accepted',
            'title' => 'Friend Request Accepted',
            'message' => "{->accepterPet->name} accepted {->requesterPet->name}'s friend request!",
            'accepter_pet_id' => $this->accepterPet->id,
            'accepter_pet_name' => $this->accepterPet->name,
            'accepter_pet_photo' => $this->accepterPet->photo,
            'requester_pet_id' => $this->requesterPet->id,
        ];
    }
}
