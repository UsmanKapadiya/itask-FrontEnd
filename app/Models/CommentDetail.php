<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class CommentDetail extends Model
{
    use SoftDeletes;

     /**
     * Get member data
     */
    function memberData()
    {
        return $this->hasOne('App\Models\UserDetail', 'id', 'commentedBy');
    }

    /**
     * Get project data.
     */

    function projectDetail()
    {
        return $this->hasOne('App\Models\ProjectTaskDetail', 'id', 'pt_id');
    }
}
