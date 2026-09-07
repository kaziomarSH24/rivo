<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;


class SendOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $otp;
    protected string $token;
    protected string $reason;
    protected string $actionUrlPath;

    /**
     * Create a new notification instance.
     *
     * @param string $otp
     * @param string $token
     * @param string $reason
     * @param string $actionUrlPath
     */
    public function __construct(string $otp, string $token, string $reason, string $actionUrlPath)
    {
        $this->otp = $otp;
        $this->token = $token;
        $this->reason = $reason;
        $this->actionUrlPath = $actionUrlPath;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Construct the verification URL
        $verificationUrl = env('FRONTEND_URL', 'http://localhost:3000') . $this->actionUrlPath . '?token=' . $this->token;

        return (new MailMessage)->markdown('emails.auth.otp_template', [
            'name' => $notifiable->name,
            'otp' => $this->otp,
            'verificationUrl' => $verificationUrl,
            'expireInMinutes' => 10,
            'reason' => $this->reason,
        ]);
    }
}
