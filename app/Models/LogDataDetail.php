<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class LogDataDetail extends Model
{
    use SoftDeletes;

    function userData()
    {
        return $this->hasOne('App\Models\LogUserDetail', 'id', 'user_id');
    }
}
