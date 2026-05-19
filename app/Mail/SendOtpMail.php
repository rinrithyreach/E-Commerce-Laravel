<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otp;

    public function __construct(string $otp)
    {
        $this->otp = $otp;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your Marketplace Account Account OTP',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "<h3>Welcome to the Marketplace!</h3>
                         <p>Your verification code is: <strong>{$this->otp}</strong></p>
                         <p>This code will expire in 10 minutes.</p>",
        );
    }
}
