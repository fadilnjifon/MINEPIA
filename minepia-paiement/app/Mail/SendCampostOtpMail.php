<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendCampostOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otpCode;

    public function __construct($otpCode)
    {
        $this->otpCode = $otpCode;
    }

    public function build()
    {
        return $this->subject('Votre code OTP de vérification CAMPOST')
                    ->view('emails.campost.otp');
    }
}
