<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class DocumentDetail extends Model
{
    use SoftDeletes;

    /**
     * Get member data
     */
    function memberData()
    {
        return $this->hasOne('App\Models\UserDetail', 'id', 'uploadedBy');
    }

    /**
     * Get project data.
     */

    function projectDetail()
    {
        return $this->hasOne('App\Models\ProjectTaskDetail', 'id', 'ptId');
    }
}
