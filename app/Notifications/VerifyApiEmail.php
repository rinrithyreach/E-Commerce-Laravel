<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

class VerifyApiEmail extends Notification
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
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // 1. Generate Laravel's secure, cryptographic temporary signed web URL (HTTP/HTTPS)
        $temporaryUrl = URL::temporarySignedRoute(
            'api.verification.verify', // Route name defined in api.php
            Carbon::now()->addMinutes(config('auth.verification.expire', 60)), // Expiration time
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        // FOR PRODUCTION / FLUTTER DEEP LINK INTERCEPT:
        // Instead of hardcoding 'myapp://', you send the standard $temporaryUrl web link.
        // Email providers will make it completely clickable because it uses http:// or https://

        return (new MailMessage)
            ->subject('Verify Your Marketplace Account')
            ->greeting("Welcome, {$notifiable->name}!")
            ->line('Thank you for registering. Please verify your email address to fully activate your marketplace account.')
            ->action('Verify Email Address', $temporaryUrl) // Passing the clickable web link here
            ->line('If you did not create an account, no further action is required.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
