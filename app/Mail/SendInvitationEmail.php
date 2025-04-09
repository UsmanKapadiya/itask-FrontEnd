<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendInvitationEmail extends Mailable
{
    use Queueable, SerializesModels;
    public $verificationcode;
    public $otp;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($verificationcode = "",$otp = "")
    {
        $this->verificationcode = $verificationcode;
        $this->otp = $otp;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('user.verification');
    }
}
