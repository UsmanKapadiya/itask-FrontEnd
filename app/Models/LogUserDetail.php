<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class LogUserDetail extends Authenticatable
{
    protected $fillable = ['name','email','password'];
    use SoftDeletes;
    use Notifiable;

    public function routeNotificationForMail($notification)
    {
        return $this->email;
    }
}
