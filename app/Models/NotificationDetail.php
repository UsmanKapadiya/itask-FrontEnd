<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class NotificationDetail extends Model
{
    use SoftDeletes;

    /**
     * Get project data.
     */

    function projectDetail()
    {
        return $this->hasOne('App\Models\ProjectTaskDetail', 'id', 'ptId');
    }

    /**
     * Get member data
     */
    function memberData()
    {
        return $this->hasOne('App\Models\UserDetail', 'id', 'sentBy');
    }
}
