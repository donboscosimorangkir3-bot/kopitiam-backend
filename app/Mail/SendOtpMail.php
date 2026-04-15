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

    // Variabel publik ini akan otomatis bisa diakses di dalam file view .blade
    public $otp;

    /**
     * Create a new message instance.
     */
    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    /**
     * Atur Judul Email (Subject)
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kode Verifikasi OTP Kopitiam 33',
        );
    }

    /**
     * Atur Tampilan/Isi Email (View)
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.otp', // Pastikan filenya ada di resources/views/emails/otp.blade.php
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
