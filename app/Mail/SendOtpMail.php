<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp; // Ini variabel untuk menampung kode OTP

    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    public function build()
    {
        return $this->subject('Kode Verifikasi OTP Kopitiam')
                    ->html("
                        <div style='font-family: Arial, sans-serif; padding: 20px;'>
                            <h2>Halo, Terima kasih telah mendaftar!</h2>
                            <p>Gunakan kode OTP di bawah ini untuk memverifikasi akun Anda:</p>
                            <h1 style='color: #2D6A4F; letter-spacing: 5px;'>{$this->otp}</h1>
                            <p>Kode ini hanya berlaku selama 10 menit. Jangan berikan kode ini kepada siapapun.</p>
                            <br>
                            <p>Salam,<br>Team Kopitiam App</p>
                        </div>
                    ");
    }
}
