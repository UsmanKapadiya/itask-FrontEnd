<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class InviteMember extends Mailable
{
    use Queueable, SerializesModels;
    public $sentBy;
    public $projectName;
    public $link;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($sentBy,$projectName,$link)
    {
        $this->sentBy = $sentBy;
        $this->projectName = $projectName;
        $this->link = $link;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('user.memberInvitation');
    }
}
