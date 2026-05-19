<?php

namespace App\Notifications;

use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage; //  CRUCIAL: Import for WebSockets
use Illuminate\Notifications\Notification;

class NewVendorApplication extends Notification
{
    use Queueable;

    public function __construct(public Vendor $vendor) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        // 💡 ADDED 'broadcast': This saves to DB, sends an email, AND fires a real-time WebSocket event!
        return ['database', 'mail', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Vendor Application: ' . $this->vendor->business_name)
            ->line("A new vendor registration request has been submitted by '{$this->vendor->business_name}'.")
            ->action('Review Application', url('/admin/vendors/'))
            ->line('Please inspect their uploaded legal documents and credentials.');
    }

    /**
     * Get the database array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'vendor_id' => $this->vendor->id,
            'business_name' => $this->vendor->business_name,
            'support_email' => $this->vendor->support_email,
            'message' => "New vendor registration submitted for '{$this->vendor->business_name}'."
        ];
    }

    /**
     * Get the real-time broadcast representation of the notification.
     * * 💡 ADDED: This streams the data instantly to the Admin dashboard over Reverb!
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id, // Pass unique notification UUID
            'type' => static::class,
            'data' => $this->toArray($notifiable), // Syncs the WebSocket data with DB data structure
        ]);
    }
}
