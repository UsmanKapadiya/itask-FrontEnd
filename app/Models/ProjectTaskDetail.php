<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;


class ProjectTaskDetail extends Model
{
    use SoftDeletes;

    /**
     * Get parent project data.
     */

    function parentProject()
    {
        return $this->hasOne('App\Models\ProjectTaskDetail', 'id', 'parentId');
    }

    /**
     * Get parent project data.
     */
    function taskTotal($id)
    {
        $childs = getChildIds($id, array());
        return ProjectTaskDetail::join('member_details', 'project_task_details.id', '=', 'member_details.ptId')->whereIn("project_task_details.id", $childs)->where("member_details.memberId", (session("user_details") ? session("user_details")->id : Auth::user()->id))->where("project_task_details.type", config("constants.type.task"))->whereRaw('member_details.deleted_at is null')->where("project_task_details.status", "!=", config("constants.project_status.completed"))->count();
    }

    /**
     * Get member data
     */
    function creatorData()
    {
        return $this->hasOne('App\Models\UserDetail', 'id', 'createdBy');
    }

    /**
     * Get association data
     */
    function memberData()
    {
        return $this->hasOne('App\Models\MemberDetail', 'ptId', 'id')->where("memberId", (session("user_details") ? session("user_details")->id : Auth::user()->id));
    }

    /**
     * Get parent association data
     */
    function parentData()
    {
        return $this->hasOne('App\Models\MemberDetail', 'ptId', 'parentId')->where("memberId", (session("user_details") ? session("user_details")->id : Auth::user()->id));
    }
}
