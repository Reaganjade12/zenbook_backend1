<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailOTP extends Notification
{
    use Queueable;

    public $otp;

    /**
     * Create a new notification instance.
     */
    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Use frontend URL instead of backend route
        $frontendUrl = config('app.frontend_url', 'https://reaganjade12.github.io/zenbook_frontend1');
        $verificationUrl = rtrim($frontendUrl, '/') . '/views/auth/verify-otp.html?email=' . urlencode($notifiable->email);
        
        return (new MailMessage)
            ->subject('Email Verification OTP')
            ->line('Hello!')
            ->line('Your email verification OTP code is:')
            ->line('**' . $this->otp . '**')
            ->line('This code will expire in 10 minutes.')
            ->action('Verify Email', $verificationUrl)
            ->line('You can also copy the code above and enter it on the verification page.')
            ->line('If you did not create an account, no further action is required.');
    }
}

