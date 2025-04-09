<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class UsersTokenDetail extends Model
{

    use Notifiable;

    public function routeNotificationForMail($notification)
    {
        return $this->email;
    }
}
