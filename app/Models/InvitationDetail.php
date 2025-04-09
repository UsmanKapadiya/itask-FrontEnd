<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class InvitationDetail extends Model
{
    use SoftDeletes;
    use Notifiable;

    public function routeNotificationForMail($notification)
    {
        return $this->memberEmailID;
    }
}
