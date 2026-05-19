<?php

namespace App\Notifications;

use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage; // Crucial for WebSockets
use Illuminate\Notifications\Notification;

class VendorApplicationApproved extends Notification
{
    use Queueable;

    public function __construct(public Vendor $vendor) {}

    public function via(object $notifiable): array
    {
        // Saves to database, sends an email, and broadcasts live over Reverb
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🎉 Your Vendor Application is Approved!')
            ->greeting("Hello {$notifiable->name},")
            ->line("Congratulations! Your application for '{$this->vendor->business_name}' has been approved.")
            ->line("Your store profile is now fully active on our marketplace.")
            ->action('Go to Store Dashboard', url('/dashboard'))
            ->line('Thank you for partnering with us!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'vendor_id' => $this->vendor->id,
            'business_name' => $this->vendor->business_name,
            'status' => 'approved',
            'message' => "Your vendor application for '{$this->vendor->business_name}' has been approved! Your store is now active."
        ];
    }

    /**
     * Real-time packet sent via WebSockets to the user
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => static::class,
            'data' => $this->toArray($notifiable),
        ]);
    }
}
