<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Pet;

class NewMatchNotification extends Notification
{
    use Queueable;

    protected $matchedPet;
    protected $myPet;

    public function __construct(Pet $matchedPet, Pet $myPet)
    {
        $this->matchedPet = $matchedPet;
        $this->myPet = $myPet;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'new_match',
            'title' => 'It\'s a Match!',
            'message' => "{->myPet->name} and {->matchedPet->name} liked each other!",
            'matched_pet_id' => $this->matchedPet->id,
            'matched_pet_name' => $this->matchedPet->name,
            'matched_pet_photo' => $this->matchedPet->photo,
            'my_pet_id' => $this->myPet->id,
        ];
    }
}
