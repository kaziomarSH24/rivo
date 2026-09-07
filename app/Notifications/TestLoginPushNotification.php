<?php

namespace App\Notifications;

use App\Notifications\Channels\FirebaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;


class TestLoginPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [FirebaseChannel::class];
    }


    //for firebase notification
    public function toFirebase($notifiable)
    {
        $tokens = $notifiable->fcmTokens()->pluck('token')->toArray();
        return [
            'title' => 'Login Successful',
            'body' => 'You have successfully logged in.',
            'tokens' => $tokens,
        ];
    }
}
