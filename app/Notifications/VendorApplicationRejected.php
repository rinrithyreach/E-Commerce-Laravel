<?php

namespace App\Notifications;

use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage; // Crucial for WebSockets
use Illuminate\Notifications\Notification;

class VendorApplicationRejected extends Notification
{
    use Queueable;

    public function __construct(public Vendor $vendor) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Update regarding your Vendor Application')
            ->greeting("Hello {$notifiable->name},")
            ->line("Thank you for your interest. We have reviewed your application for '{$this->vendor->business_name}'.")
            ->line("Regrettably, we were unable to approve your application at this time because your business credentials could not be verified.")
            ->line('Please review your legal documents and feel free to submit a new application.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'vendor_id' => $this->vendor->id,
            'business_name' => $this->vendor->business_name,
            'status' => 'rejected',
            'message' => "Your vendor application for '{$this->vendor->business_name}' was rejected. Please check your verification documents."
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
