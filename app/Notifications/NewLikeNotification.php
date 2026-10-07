<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Pet;

class NewLikeNotification extends Notification
{
    use Queueable;

    protected $likerPet;
    protected $targetPet;

    public function __construct(Pet $likerPet, Pet $targetPet)
    {
        $this->likerPet = $likerPet;
        $this->targetPet = $targetPet;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'new_like',
            'title' => 'New Like!',
            'message' => "Someone liked {->targetPet->name}!",
            'liker_pet_id' => $this->likerPet->id,
            'target_pet_id' => $this->targetPet->id,
        ];
    }
}
