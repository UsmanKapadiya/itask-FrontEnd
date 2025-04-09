<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class QueuedNotificationDetail extends Model
{
    /**
     * Get member data
     */
    function memberData()
    {
        return $this->hasOne('App\Models\UserDetail', 'id', 'created_by');
    }

    /**
     * Get project data.
     */

    function projectDetail()
    {
        return $this->hasOne('App\Models\ProjectTaskDetail', 'id', 'pt_id');
    }
}
