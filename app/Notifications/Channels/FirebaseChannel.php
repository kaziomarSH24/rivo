<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Illuminate\Support\Facades\Log;

class FirebaseChannel
{

     public function send($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toFirebase')) {
            return;
        }

        try {
            $messaging = app('firebase.messaging');
            $fcmData = $notification->toFirebase($notifiable);

            $tokens = $fcmData['tokens'] ?? [];

            if (empty($tokens)) {
                Log::info('User has no device tokens.', [
                    'user_id' => $notifiable->id ?? null,
                ]);
                return;
            }

            $message = CloudMessage::new()
                ->withNotification([
                    'title' => $fcmData['title'],
                    'body'  => $fcmData['body'],
                ])
                ->withData($fcmData['data'] ?? []);

            $report = $messaging->sendMulticast($message, $tokens);

            Log::info('Multicast notification sent.', [
                'user_id' => $notifiable->id ?? null,
                'successful_sends' => $report->successes()->count(),
                'failed_sends' => $report->failures()->count(),
            ]);

        } catch (MessagingException | FirebaseException $e) {
            Log::error('Firebase multicast notification failed.', [
                'user_id' => $notifiable->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
