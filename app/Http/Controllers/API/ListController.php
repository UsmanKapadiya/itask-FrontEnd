<?php

namespace App\Http\Controllers\Api;

use App\Models\CommentDetail;
use App\Models\DocumentDetail;
use App\Models\MemberDetail;
use App\Models\NotificationDetail;
use App\Models\NotificationSettingDetail;
use App\Models\ProjectTaskDetail;
use App\Models\Tags;
use App\Models\UserDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Validator;

class ListController extends Controller
{
    /**
     *  Method to get project lists
     *
     */
    public function unassignTaskList(Request $request)
    {
        try {
            $status = $request->input('completed');
            $data = $this->getChildOfInboxTasks($status, 0);
            $tasks = array();
            foreach ($data as $d) {
                $single_task = $this->getTaskDetails($d);
                $single_task["children"] = $this->getRecursiveInboxTasks($status, $d->id, array());
                array_push($tasks, $single_task);
            }
            return json_encode($tasks);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }


    public function projectList($is_inner_call = false, $is_completed = 0)
    {
        try {
            if (!$is_inner_call && !session('user_details')) {
                return $this->sendResultJSON('11', 'Unauthorised');
            }
            $userId = session('user_details')->id;
            $member_projects = MemberDetail::selectRaw('id,ptId')->where('memberId', $userId)->orderBy('id', 'desc')->get();
            $projects = array();
            $not_assigned_parent_projects = array();
            foreach (count($member_projects) > 0 ? $member_projects : array() as $m) {
                if (isset($m->projectDetail) && $m->projectDetail->type == config('constants.type.project') && ($is_completed ? $m->projectDetail->status == config('constants.project_status.completed') : $m->projectDetail->status != config('constants.project_status.completed'))) {
                    $project_detail = $m->projectDetail;
                    if ($project_detail->status == config('constants.project_status.review') && $project_detail->createdBy == $userId) {
                        continue;
                    }
                    if ($project_detail->parentId == 0) {
                        array_push($projects, $this->getChildProjects($project_detail, $is_completed));
                    } elseif (!$project_detail->parentData) {
                        $project_data = $this->getChildProjects($project_detail, $is_completed, 1);
                        $child_projects = array();
                        if (count($project_data['child_projects']) > 0) {
                            $child_projects = $this->pushChildData($project_data['child_projects'], $child_projects);
                            $project_data['child_projects'] = array();
                            array_push($not_assigned_parent_projects, $project_data);
                            foreach (count($child_projects) > 0 ? $child_projects : array() as $cp) {
                                array_push($not_assigned_parent_projects, $cp);
                            }
                        } else {
                            array_push($not_assigned_parent_projects, $project_data);
                        }
                    }
                }
            }
            $projects = array_merge($projects, $not_assigned_parent_projects);
            if (!$is_inner_call) {
                return $this->sendResultJSON('1', '', array(
                    'projects' => $projects
                ));
            } else {
                return $projects;
            }
        } catch (\Exception $e) {
            return $this->sendResultJSON('0', $e->getMessage());
        }
    }

    private function pushChildData($child_data, $result)
    {
        foreach (count($child_data) > 0 ? $child_data : array() as $c) {
            if (count($c['child_projects']) > 0) {
                $child_projects = $c['child_projects'];
                $c['child_projects'] = array();
                array_push($result, $c);
                $result = $this->pushChildData($child_projects, $result);
            } else {
                array_push($result, $c);
            }
        }

        return $result;
    }

    private function getChildProjects($project_details, $is_completed = 0, $level = 0)
    {
        $userId = session('user_details')->id;
        $type = config('constants.type.project');
        $child_data = ProjectTaskDetail::where('type', $type)
            ->where('parentLevel', $project_details->parentLevel + 1)
            ->where('parentId', $project_details->id);
        if ($is_completed) {
            $child_data = $child_data->where('status', config('constants.project_status.completed'));
        } else {
            $child_data = $child_data->where('status', '!=', config('constants.project_status.completed'));
        }
        $child_data = $child_data->orderBy('ptOrder', 'asc')->get();
        $child_projects = array();
        foreach (count($child_data) > 0 ? $child_data : array() as $c) {
            if ($c->memberData) {
                if ($c->status == config('constants.project_status.review') && $c->createdBy == $userId) {
                    continue;
                }
                array_push($child_projects, $this->getChildProjects($c, $is_completed, $level));
            }
        }
        $status = getProjectStatus($project_details);
        return array(
            'id' => $project_details->id,
            'name' => $project_details->name,
            'color' => $project_details->color,
            'level' => $level == 0 ? $project_details->parentLevel : 1,
            'actual_project_level' => $project_details->parentLevel,
            'parent_id' => $project_details->parentId,
            'is_creator_of_project' => $project_details->createdBy == $userId ? 1 : 0,
            'no_of_tasks' => $project_details->taskTotal($project_details->id),
            'status' => $status['status'],
            'is_overdue' => $status['is_overdue'],
            'child_projects' => $child_projects
        );
    }

    /**
     *  Method to get task lists from project id
     *
     */
    public function getTaskByProjectID(Request $request)
    {
        try {
            if (!session('user_details')) {
                return $this->sendResultJSON('11', 'Unauthorised');
            }
            $validator = Validator::make($request->all(),
                ['ptId' => 'required'],
                ['ptId.required' => 'Please enter project id']
            );
            if ($validator->fails()) {
                return $this->sendResultJSON('2', $validator->errors()->first());
            }
            $parentId = $request->input('ptId');
            $project_data = ProjectTaskDetail::where('id', $parentId)->first();
            if (!$project_data) {
                return $this->sendResultJSON('2', 'Project/task not found');
            }
            $userId = session('user_details')->id;
            $is_member = MemberDetail::where('ptId', $parentId)->where('memberId', $userId)->count();
            if ($is_member == 0) {
                return $this->sendResultJSON('3', 'Access denied');
            }
            $project_status = config("constants.project_status");

            $taskList = $this->recursiveOfTask($request, $parentId, $project_data->status);
            $all_data = array();
            foreach (count($taskList) > 0 ? $taskList : array() as $task) {
                $single_task = listMethodData($task, $userId);
                $single_task["childs"] = $this->getChildsTask($task, $request, $project_data->status, array());
                $single_task["maxChildLevel"] = $this->findMaxChildLevel($single_task, 0);
                $all_data[$single_task["order"]] = $single_task;
            }

            $is_review = 0;
            if ($request->input('is_display_review') != null) {
                $is_review = intval($request->input('is_display_review'));
            } else if (array_search($project_data->status, $project_status) != "review" && array_search($project_data->status, $project_status) != "completed") {
                $is_review = 0;
            }
            $sub_project_details = $this->recursiveOfSubProject($request, $project_data, $project_data->status);
            foreach (count($sub_project_details) > 0 ? $sub_project_details : array() as $p) {
                if (!$is_review) {
                    if ($p->status == $project_status["review"] && $p->createdBy == $userId)
                        continue;
                }
                $single_subproject = listMethodData($p, $userId);
                $single_subproject["childs"] = $this->getChildsSubProject($request, $p, $project_data->status, $is_review, array());
                $single_subproject["maxChildLevel"] = $this->findMaxChildLevel($single_subproject, 0);
                $all_data[$single_subproject["order"]] = $single_subproject;
            }
            ksort($all_data);
            return $this->sendResultJSON('1', '', array(
                'data' => array_values($all_data),
                'members' => getMembers($parentId),
                'isCreator' => $project_data->createdBy == $userId ? 1 : 0,
                'level' => $project_data->parentLevel,
                'status' => $project_data->status,
                'repeat' => $project_data->repeat,
                'all_parent_ids' => getBaseParentId($project_data, array(), 1)
            ));
        } catch (\Exception $e) {
            return $this->sendResultJSON('0', $e->getMessage());
        }
    }

    private function recursiveOfTask($request, $parentId, $parent_status)
    {
        $project_status = config("constants.project_status");
        $taskList = ProjectTaskDetail::whereHas("memberData")->where('parentId', $parentId)->where('type', config('constants.type.task'));
        if ($request->input('is_completed') != null) {
            $is_completed = $request->input('is_completed');
            if (!$is_completed) {
                $taskList = $taskList->where('status', '!=', $project_status["completed"]);
            }
        } else {
            if (array_search($parent_status, $project_status) != "review" && array_search($parent_status, $project_status) != "completed") {
                $taskList = $taskList->where('status', '!=', $project_status["completed"]);
            }
        }
        $taskList = $taskList->orderBy('ptOrder', 'asc')->get();
        return $taskList;
    }

    private function getChildsTask($tasks, $request, $parent_status, $result)
    {
        $child_data = $this->recursiveOfTask($request, $tasks->id, $parent_status);
        foreach (count($child_data) > 0 ? $child_data : array() as $t) {
            $single_task = listMethodData($t, session('user_details')->id);
            $single_task["childs"] = $this->getChildsTask($t, $request, $parent_status, array());
            $single_task["maxChildLevel"] = $this->findMaxChildLevel($single_task, 0);
            array_push($result, $single_task);
        }
        return $result;
    }

    private function recursiveOfSubProject($request, $project_data, $parent_project_status)
    {
        $project_status = config("constants.project_status");
        $sub_project_details = ProjectTaskDetail::whereHas("memberData")->where('parentId', $project_data->id)->where('type', config('constants.type.project'))->where('parentLevel', intval($project_data->parentLevel) + 1);
        if ($request->input('is_completed_project') != null) {
            $is_project_completed = $request->input('is_completed_project');
            if (!$is_project_completed) {
                $sub_project_details = $sub_project_details->where('status', '!=', $project_status["completed"]);
            }
        } else {
            if (array_search($parent_project_status, $project_status) != "review" && array_search($parent_project_status, $project_status) != "completed") {
                $sub_project_details = $sub_project_details->where('status', '!=', $project_status["completed"]);
            }
        }
        $sub_project_details = $sub_project_details->orderBy('ptOrder', 'asc')->get();
        return $sub_project_details;
    }

    private function getChildsSubProject($request, $project_data, $parent_project_status, $is_review, $result)
    {
        $project_status = config("constants.project_status");
        $userId = session('user_details')->id;
        $sub_tasks_data = $this->recursiveOfTask($request, $project_data->id, $project_data->status);
        foreach (count($sub_tasks_data) > 0 ? $sub_tasks_data : array() as $st) {
            $single_task = listMethodData($st, $userId);
            $single_task["childs"] = $this->getChildsTask($st, $request, $project_data->status, array());
            $single_task["maxChildLevel"] = $this->findMaxChildLevel($single_task, 0);
            $result[$single_task["order"]] = $single_task;
        }
        $sub_projects_data = $this->recursiveOfSubProject($request, $project_data, $parent_project_status);
        foreach (count($sub_projects_data) > 0 ? $sub_projects_data : array() as $sp) {
            if (!$is_review) {
                if ($sp->status == $project_status["review"] && $sp->createdBy == $userId)
                    continue;
            }
            $single_project = listMethodData($sp, $userId);
            $single_project["childs"] = $this->getChildsSubProject($request, $sp, $parent_project_status, $is_review, array());
            $single_project["maxChildLevel"] = $this->findMaxChildLevel($single_project, 0);
            $result[$single_project["order"]] = $single_project;
        }
        ksort($result);
        return array_values($result);
    }

    public function findMaxChildLevel($data, $count)
    {
        if (count($data["childs"]) > 0) {
            if (count($data["childs"]) == 1) {
                $count = $count + 1;
                foreach ($data["childs"] as $d) {
                    $count = $this->findMaxChildLevel($d, $count);
                }
            } else {
                $count = $this->findMaxFromChild($data);
            }
        }
        return $count;
    }

    private function findMaxFromChild($projects)
    {
        $max = (count($projects["childs"]) > 0 ? 1 : 0);
        $in_child_count = 0;
        foreach (count($projects["childs"]) > 0 ? $projects["childs"] : array() as $d) {
            if ($d["maxChildLevel"] == 0) {
                $in_child_count = $in_child_count + 1;
            }
            if ($d["maxChildLevel"] > $max) {
                $max = $d["maxChildLevel"];
            }
        }
        return ($in_child_count != count($projects["childs"]) ? ($max + 1) : $max);
    }

    /**
     *  Method to get task lists, group by priority
     *
     */
    public function getTasksByFlag(Request $request)
    {
        try {
            if (!session('user_details')) {
                return $this->sendResultJSON('11', 'Unauthorised');
            }
            $priority = $request->input('priority') ?? 1;
            $userId = session('user_details')->id;
            $is_completed = $request->input('is_completed') ?? 0;
            $completed_status = config('constants.project_status.completed');
            $all_tasks = ProjectTaskDetail::join('member_details', 'project_task_details.id', '=', 'member_details.ptId')
                ->selectRaw('project_task_details.*,member_details.memberId')
                ->where('project_task_details.flag', $priority);
            if (!$is_completed) {
                $all_tasks = $all_tasks->where('project_task_details.status', '!=', $completed_status);
            }
            $all_tasks = $all_tasks
                ->where('member_details.memberId', $userId)
                ->where('project_task_details.type', config('constants.type.task'))
                ->whereRaw('member_details.deleted_at is null')
                ->orderBy('project_task_details.id', 'desc')
                ->get();

            $tasks = array();
            $completed_tasks = array();
            foreach (count($all_tasks) > 0 ? $all_tasks : array() as $t) {
                $data = listMethodData($t, $userId);
                if ($t->status == $completed_status) {
                    array_push($completed_tasks, $data);
                } else {
                    array_push($tasks, $data);
                }
            }
            $tasks = array_merge($tasks, $completed_tasks);

            $is_completed_project = $request->input('is_completed_project') ?? 0;
            $is_review = $request->input('is_display_review') ?? 0;
            $projectTaskList = ProjectTaskDetail::join('member_details', 'project_task_details.id', '=', 'member_details.ptId')
                ->selectRaw('project_task_details.*,member_details.memberId')
                ->where('project_task_details.flag', $priority)
                ->where('member_details.memberId', $userId)
                ->where('project_task_details.type', config('constants.type.project'))
                ->whereRaw('member_details.deleted_at is null');
            if (!$is_completed_project) {
                $projectTaskList = $projectTaskList->where('project_task_details.status', '!=', $completed_status);
            }
            if (!$is_review) {
                $projectTaskList = $projectTaskList->where('project_task_details.status', '!=', config("constants.project_status.review"));
            }
            $projectTaskList = $projectTaskList->orderBy('project_task_details.id', 'desc')->get();
            $projects = array();
            $completed_projects = array();
            $review_projects = array();
            foreach (count($projectTaskList) > 0 ? $projectTaskList : array() as $p) {
                $data = listMethodData($p, $userId);
                if ($p->status == config("constants.project_status.completed")) {
                    array_push($completed_projects, $data);
                } else if ($p->status == config("constants.project_status.review")) {
                    array_push($review_projects, $data);
                } else {
                    array_push($projects, $data);
                }
            }
            $projects = array_merge($projects, $review_projects, $completed_projects);
            return $this->sendResultJSON('1', '', array(
                'tasks' => $tasks,
                'projects' => $projects,
                'uncompletedTaskCount' => count($tasks) - count($completed_tasks),
                'completedTaskCount' => count($completed_tasks)
            ));
        } catch (\Exception $e) {
            return $this->sendResultJSON('0', $e->getMessage());
        }
    }

    /**
     *  Method to get inboxed task lists
     *
     */
    public function getInboxTasks(Request $request)
    {
        try {
            if (!session('user_details')) {
                return $this->sendResultJSON('11', 'Unauthorised');
            }
            $userId = session('user_details')->id;
            $status = $request->input('is_completed') ?? 0;
            $taskList = $this->getChildOfInboxTasks($status, 0);
            $task_detail = array();
            foreach (count($taskList) > 0 ? $taskList : array() as $task) {
                $single_task = listMethodData($task, $userId);
                $single_task["childs"] = $this->getRecursiveInboxTasks($status, $task->id, array());
                $single_task["maxChildLevel"] = $this->findMaxChildLevel($single_task, 0);
                array_push($task_detail, $single_task);
            }
            return $this->sendResultJSON('1', '', array(
                'tasks' => $task_detail,
                'uncompletedTaskCount' => 0,
                'completedTaskCount' => 0
            ));
        } catch (\Exception $e) {
            return $this->sendResultJSON('0', $e->getMessage());
        }
    }

    private function getChildOfInboxTasks($status, $parent_id)
    {
        $taskList = ProjectTaskDetail::where('parentId', $parent_id)->where('type', config('constants.type.task'))->where('createdBy', session('user_details')->id);
        if (!$status) {
            $taskList = $taskList->where('status', "!=", config('constants.project_status.completed'));
        }
        $taskList = $taskList->orderBy('ptOrder', 'asc')->get();
        return $taskList;
    }

    private function getRecursiveInboxTasks($status, $parent_id, $result)
    {
        $child_data = $this->getChildOfInboxTasks($status, $parent_id);
        foreach (count($child_data) > 0 ? $child_data : array() as $t) {
            $single_task = listMethodData($t, session('user_details')->id);
            $single_task["childs"] = $this->getRecursiveInboxTasks($status, $t->id, array());
            $single_task["maxChildLevel"] = $this->findMaxChildLevel($single_task, 0);
            array_push($result, $single_task);
        }
        return $result;
    }

    /**
     *  Method to projects under review
     *
     */
    public function getUnderReviewProjects()
    {
        try {
            if (!session('user_details')) {
                return $this->sendResultJSON('11', 'Unauthorised');
            }
            $userId = session('user_details')->id;
            $projects = array();
            $project_details = ProjectTaskDetail::where('type', config('constants.type.project'))
                ->where('status', '=', config('constants.project_status.review'))
                ->where('createdBy', $userId)
                ->orderBy('id', 'desc')
                ->get();
            foreach (count($project_details) > 0 ? $project_details : array() as $p) {
                array_push($projects, listMethodData($p, $userId));
            }
            return $this->sendResultJSON('1', '', array('projects' => $projects));
        } catch (\Exception $e) {
            return $this->sendResultJSON('0', $e->getMessage());
        }
    }

    /**
     *  Method to get project member data
     *
     */
    public function projectTaskMemberList(Request $request)
    {
        try {
            if (!session('user_details')) {
                return $this->sendResultJSON('11', 'Unauthorised');
            }
            $validator = Validator::make(
                $request->all(),
                [
                    'ptId' => 'required'
                ],
                [
                    'ptId.required' => 'Please select project/task'
                ]
            );
            if ($validator->fails()) {
                return $this->sendResultJSON(
                    '2',
                    $validator->errors()->first()
                );
            }
            $ptid = $request->input('ptId');
            $get_project_details = ProjectTaskDetail::where(
                'id',
                $ptid
            )->first();
            if (!$get_project_details) {
                return $this->sendResultJSON('2', 'Data not found');
            }
            $member_details = MemberDetail::join(
                'user_details',
                'member_details.memberId',
                '=',
                'user_details.id'
            )
                ->selectRaw(
                    'user_details.id,user_details.name,user_details.email'
                )
                ->where('user_details.id', '!=', session('user_details')->id)
                ->whereRaw('user_details.deleted_at IS NULL')
                ->where('ptId', $ptid)
                ->get();
            $members = array();
            foreach (
                count($member_details) > 0 ? $member_details : array()
                as $m
            ) {
                array_push($members, array(
                    'id' => $m->id,
                    'name' => $m->name ?? $m->email,
                    'email' => $m->email
                ));
            }
            return $this->sendResultJSON('1', '', array('members' => $members));
        } catch (\Exception $e) {
            return $this->sendResultJSON('0', $e->getMessage());
        }
    }

    /**
     *  Method to get tag lists of particular user
     *
     */
    public function taglist(Request $request, $is_inner_call = false)
    {
        if (!$is_inner_call && !session('user_details')) {
            return $this->sendResultJSON('11', 'Unauthorised');
        }
        $userId = session('user_details')->id;
        $tags_array = array();
        $tags = Tags::selectRaw('id,tagName')
            ->where('userId', $userId)
            ->orderBy('id', 'desc')
            ->get();
        foreach (count($tags) > 0 ? $tags : array() as $t) {
            array_push($tags_array, array(
                'id' => $t->id,
                'name' => $t->tagName
            ));
        }
        if (!$is_inner_call) {
            return $this->sendResultJSON('1', '', array('tags' => $tags_array));
        } else {
            return $tags_array;
        }
    }

    /**
     *  Method to get project and tag data
     *
     */
    public function projectTagList(Request $request)
    {
        try {
            if (!session('user_details')) {
                return $this->sendResultJSON('11', 'Unauthorised');
            }
            $permissions = config('permission.permissions');
            $all_permissions = array();
            foreach ($permissions as $key => $value) {
                $child_permissions = array();
                foreach ($value as $c => $v) {
                    $child_permissions[$c] =
                        $key == 'task'
                            ? (count($v) == 3
                            ? 'all'
                            : end($v))
                            : end($v);
                }
                $all_permissions[$key] = $child_permissions;
            }
            $user = session('user_details');
            $notification_count = NotificationDetail::where('sentTo', session('user_details')->id)->where('isRead', 0)->count();
            $user_notification_setting = NotificationSettingDetail::where(
                'userId',
                $user->id
            )->get();
            $notification_settings = config('constants.notification_setting');
            $notification_setting_array = array();
            foreach ($notification_settings as $key => $n) {
                $notification_setting_array[$key] = array(
                    'email' => 1,
                    'push_notification' => 1,
                    'key_val' => $key,
                    'title' => $n
                );
            }
            foreach (
                count($user_notification_setting) > 0
                    ? $user_notification_setting
                    : array()
                as $un
            ) {
                $notification_setting_array[$un->notificationType]['email'] =
                    $un->email;
                $notification_setting_array[$un->notificationType]['push_notification'] = $un->pushNotification;
            }
            $tags_data = ProjectTaskDetail::selectRaw('GROUP_CONCAT(tags) as tag_ids')->whereRaw('(tags IS NOT NULL and tags != "")')->where("createdBy", $user->id)->first();
            $tags_array = array();
            if ($tags_data) {
                $tags_id_array = explode(",", $tags_data->tag_ids);
                $tags_id_array = array_unique($tags_id_array);
                $tags = Tags::whereRaw("(".(count($tags_id_array) > 0 ? "id IN (" .  "'" . implode ( "', '", $tags_id_array ) . "'" . ") OR " :""). "created_at >= '" . Carbon::now()->subDay() . "')")->orderBy('id', 'desc')->get();
                foreach (count($tags) > 0 ? $tags : array() as $t) {
                    array_push($tags_array, array(
                        'id' => $t->id,
                        'name' => $t->tagName
                    ));
                }
            }
            return $this->sendResultJSON('1', '', array(
                'projects' => $this->projectList(true),
                'completed_projects' => $this->getCompleteProjects(),
                'tags' => $this->taglist($request, true),
                'sidebar_tags' => $tags_array,
                'permissions' => $all_permissions,
                'notifications' => $notification_count,
                'notification_settings' => array_values(
                    $notification_setting_array
                ),
                'timezones' => \DateTimeZone::listIdentifiers(),
                'default_reminder' => $user->automatic_reminder,
                'remind_via_email' => $user->remind_via_email,
                'remind_via_mobile_notification' =>
                    $user->remind_via_mobile_notification,
                'remind_via_desktop_notification' =>
                    $user->remind_via_desktop_notification,
                'user_name' => $user->name
            ));
        } catch (\Exception $e) {
            return $this->sendResultJSON('0', $e->getMessage());
        }
    }

    public function getCompleteProjects()
    {
        $userId = session('user_details')->id;
        $member_projects = ProjectTaskDetail::join('member_details', 'project_task_details.id', '=', 'member_details.ptId')->selectRaw('project_task_details.*')->where('member_details.memberId', $userId)->where('project_task_details.type', config('constants.type.project'))->whereRaw("member_details.deleted_at IS NULL")->where('project_task_details.status', config('constants.project_status.completed'))->orderBy('project_task_details.id', 'desc')->get();
        $projects = array();
        $not_assigned_parent_projects = array();
        foreach (count($member_projects) > 0 ? $member_projects : array() as $m) {
            if ($m->parentId == 0) {
                array_push($projects, $this->getChildProjects($m, 1));
            } else {
                if ($m->parentData) {
                    if ($m->parentProject && $m->parentProject->status == config('constants.project_status.completed')) {
                        continue;
                    } else {
                        $child_projects = $this->getChildProjects($m, 1);
                        $child_projects = $this->changeLevel($child_projects, 1);
                        array_push($projects, $child_projects);
                    }
                } else {
                    $project_data = $this->getChildProjects($m, 1, 1);
                    $child_projects = array();
                    if (count($project_data['child_projects']) > 0) {
                        $child_projects = $this->pushChildData($project_data['child_projects'], $child_projects);
                        $project_data['child_projects'] = array();
                        array_push($not_assigned_parent_projects, $project_data);
                        foreach (count($child_projects) > 0 ? $child_projects : array() as $cp) {
                            array_push($not_assigned_parent_projects, $cp);
                        }
                    } else {
                        array_push($not_assigned_parent_projects, $project_data);
                    }
                }
            }
        }
        $projects = array_merge($projects, $not_assigned_parent_projects);
        return $projects;
    }

    private function changeLevel($projects, $level)
    {
        $projects['level'] = $level;
        foreach (count($projects["child_projects"]) > 0 ? $projects["child_projects"] : array() as $index => $p) {
            $level = $level + 1;
            $projects["child_projects"][$index] = $this->changeLevel($p, $level);
        }
        return $projects;
    }

    public function documentListByProjectId(Request $request)
    {
        try {
            if (!session('user_details')) {
                return $this->sendResultJSON('11', 'Unauthorised');
            }
            $validator = Validator::make(
                $request->all(),
                ['ptId' => 'required'],
                ['ptId.required' => 'Please select project/task']
            );

            if ($validator->fails()) {
                return $this->sendResultJSON('2', $validator->errors()->first());
            }

            $pt_id = $request->input('ptId');
            $project_details = ProjectTaskDetail::where('id', $pt_id)->first();
            if (!$project_details) {
                return $this->sendResultJSON('2', 'Project/task not Found');
            }
            $document_array = array();
            $documents = DocumentDetail::where('ptId', $pt_id)->orderBy('id', 'desc')->get();
            foreach (count($documents) > 0 ? $documents : array() as $doc) {
                $base_url = asset('uploads') . '/' . $doc->ptId . '/' . $doc->formatted_name;
                array_push($document_array, array(
                    'id' => $doc->id,
                    'name' => splitDocumentName($doc->original_name),
                    'original_name' => $doc->original_name,
                    'size' => $doc->size,
                    'type' => getDocumentType($doc->type),
                    'baseUrl' => $base_url,
                    'thumbUrl' => $doc->videoThumbUrl != null ? (asset('uploads') . '/' . $doc->ptId . '/thumbnail/' . $doc->videoThumbUrl) : '',
                    'uploadedByName' => $doc->memberData->name,
                    'uploadedBy' => $doc->uploadedBy,
                    'uploadedTime' => convertAttachmentDate(session('user_details')->timezone, $doc->uploadedTime)
                ));
            }
            $result_data = array(
                'documents' => $document_array,
                'members' => getMembers($pt_id)
            );
            $result_data['type'] = array_search($project_details->type, config('constants.type'));
            $result_data['level'] = $project_details->parentLevel;
            $result_data['isCreator'] = $project_details->createdBy == session('user_details')->id ? 1 : 0;
            $isAssigned = 0;
            if ($result_data['type'] == 'task') {
                $is_member = MemberDetail::where('ptId', $project_details->id)->where('memberId', session('user_details')->id)->count();
                $isAssigned = $is_member ? 1 : 0;
            }
            $result_data['isAssigned'] = $isAssigned;
            return $this->sendResultJSON('1', '', $result_data);
        } catch (\Exception $e) {
            return $this->sendResultJSON('0', $e->getMessage());
        }
    }

    /**
     * Get comments list by project id
     */
    public function getCommentsByProjectId(Request $request)
    {
        try {
            if (!session('user_details')) {
                return $this->sendResultJSON('11', 'Unauthorised');
            }
            $validator = Validator::make(
                $request->all(),
                [
                    'ptId' => 'required'
                ],
                [
                    'ptId.required' => 'Please select project/task'
                ]
            );
            if ($validator->fails()) {
                return $this->sendResultJSON(
                    '2',
                    $validator->errors()->first()
                );
            }
            $ptid = $request->input('ptId');
            $userId = session('user_details')->id;
            $project_details = ProjectTaskDetail::where('id', $ptid)->first();
            if (!$project_details) {
                return $this->sendResultJSON('2', 'Project/task not Found');
            }
            if ($project_details->parentId != 0) {
                $is_member = MemberDetail::where(
                    'ptId',
                    $project_details->type == config('constants.type.project')
                        ? $ptid
                        : $project_details->parentId
                )
                    ->where('memberId', $userId)
                    ->count();
                if ($is_member == 0) {
                    return $this->sendResultJSON('3', 'Access denied');
                }
            }
            $comment_details = CommentDetail::where('pt_id', $ptid)
                ->where('parentId', 0)
                ->orderBy('id', 'desc')
                ->get();
            $comments = array();
            foreach (
                count($comment_details) > 0 ? $comment_details : array()
                as $c
            ) {
                array_push($comments, array(
                    'id' => $c->id,
                    'comment' => $c->comment,
                    'parentID' => $c->parentId,
                    'level' => $c->parentLevel,
                    'documentName' => $c->documentName,
                    'documentSize' => number_format($c->documentSize, 2),
                    'documentType' => $c->documentType != '' ? getDocumentType($c->documentType) : null,
                    'documentURL' => $c->documentName != '' ? asset('uploads') . '/' . $ptid . '/comment/' . $c->documentName : null,
                    'documentThumbUrl' => $c->documentThumbUrl != '' ? asset('uploads') . '/' . $ptid . '/comment/thumbnail/' . $c->documentThumbUrl : null,
                    'commentedByUserId' => $c->commentedBy,
                    'commentedBy' => $c->memberData->name,
                    'commentedTime' => convertCommentDate(session('user_details')->timezone, $c->commentedTime)
                ));

                $child_comments = CommentDetail::where('pt_id', $ptid)
                    ->where('parentId', $c->id)
                    ->orderBy('id', 'desc')
                    ->get();
                foreach (
                    count($child_comments) > 0 ? $child_comments : array()
                    as $cc
                ) {
                    array_push($comments, array(
                        'id' => $cc->id,
                        'comment' => $cc->comment,
                        'parentID' => $cc->parentId,
                        'level' => $cc->parentLevel,
                        'documentName' => $cc->documentName,
                        'documentSize' => number_format($cc->documentSize, 2),
                        'documentType' =>
                            $cc->documentType != ''
                                ? getDocumentType($cc->documentType)
                                : null,
                        'documentURL' =>
                            $cc->documentName != ''
                                ? asset('uploads') .
                                '/' .
                                $ptid .
                                '/comment/' .
                                $cc->documentName
                                : null,
                        'documentThumbUrl' =>
                            $cc->documentThumbUrl != ''
                                ? asset('uploads') .
                                '/' .
                                $ptid .
                                '/comment/thumbnail/' .
                                $cc->documentThumbUrl
                                : null,
                        'commentedByUserId' => $cc->commentedBy,
                        'commentedBy' => $cc->memberData->name,
                        'commentedTime' => convertCommentDate(session('user_details')->timezone, $cc->commentedTime)
                    ));
                }
            }

            $result_data = array(
                'comments' => $comments,
                'members' => getMembers($ptid)
            );
            $result_data['type'] = array_search($project_details->type, config('constants.type'));
            $result_data['level'] = $project_details->parentLevel;
            $result_data['isCreator'] = $project_details->createdBy == $userId ? 1 : 0;
            $isAssigned = 0;
            if ($result_data['type'] == 'task') {
                $is_member = MemberDetail::where('ptId', $project_details->id)->where('memberId', $userId)->count();
                $isAssigned = $is_member ? 1 : 0;
            }
            $result_data['isAssigned'] = $isAssigned;

            return $this->sendResultJSON('1', '', $result_data);
        } catch (\Exception $e) {
            return $this->sendResultJSON('0', $e->getMessage());
        }
    }

    /**
     * Get projects for add sub project/add task
     */
    public function getProjectsForAdd(Request $request)
    {
        try {
            if (!session('user_details')) {
                return $this->sendResultJSON('11', 'Unauthorised');
            }
            $validator = Validator::make(
                $request->all(),
                [
                    'type' => 'required'
                ],
                [
                    'type.required' => 'Please enter type'
                ]
            );
            if ($validator->fails()) {
                return $this->sendResultJSON(
                    '2',
                    $validator->errors()->first()
                );
            }
            $type = $request->input('type');
            $permission =
                $type == 'project'
                    ? config('permission.permissions.project.add_sub_project')
                    : config('permission.permissions.project.add_task');
            if (count($permission) == 1 && in_array('creator', $permission)) {
                $all_projects = $this->getCreatorProjects();
            } else {
                $all_projects = $this->projectList(true);
            }
            return $this->sendResultJSON('1', '', array(
                'projects' => $all_projects
            ));
        } catch (\Exception $e) {
            return $this->sendResultJSON('0', $e->getMessage());
        }
    }

    private function getCreatorProjects()
    {
        $userId = session('user_details')->id;
        $project_type = config('constants.type.project');
        $projects = array();
        $allProjects = ProjectTaskDetail::selectRaw(
            'id,name,parentId,parentLevel,color,createdBy'
        )
            ->where('createdBy', $userId)
            ->where('type', $project_type)
            ->whereNotIn('status', array(
                config('constants.project_status.completed'),
                config('constants.project_status.review')
            ))
            ->orderBy('id', 'desc')
            ->get();
        $not_assigned_parent_projects = array();
        foreach (count($allProjects) > 0 ? $allProjects : array() as $p) {
            if ($p->parentId == 0) {
                array_push(
                    $projects,
                    $this->getCreateChildProjects($p, $p->parentLevel + 1)
                );
            } elseif (!$p->parentData) {
                array_push($not_assigned_parent_projects, array(
                    'id' => $p->id,
                    'name' => $p->name,
                    'color' => $p->color,
                    'level' => 1,
                    'actual_project_level' => $p->parentLevel,
                    'parent_id' => $p->parentId,
                    'is_creator_of_project' => $p->createdBy == $userId ? 1 : 0,
                    'child_projects' => array()
                ));
            }
        }
        $projects = array_merge($projects, $not_assigned_parent_projects);
        return $projects;
    }

    private function getCreateChildProjects($project_details, $level)
    {
        $userId = session('user_details')->id;
        $project_type = config('constants.type.project');
        $childProjects = ProjectTaskDetail::selectRaw(
            'id,name,parentId,parentLevel,color,createdBy'
        )
            ->where('createdBy', $userId)
            ->where('parentLevel', $level)
            ->where('type', $project_type)
            ->whereNotIn('status', array(
                config('constants.project_status.completed'),
                config('constants.project_status.review')
            ))
            ->where('parentId', $project_details->id)
            ->orderBy('ptOrder', 'asc')
            ->get();
        $childProjectArray = array();
        foreach (count($childProjects) > 0 ? $childProjects : array() as $c) {
            array_push(
                $childProjectArray,
                $this->getCreateChildProjects($c, $c->parentLevel + 1)
            );
        }
        return array(
            'id' => $project_details->id,
            'name' => $project_details->name,
            'color' => $project_details->color,
            'level' => $project_details->parentLevel,
            'actual_project_level' => $project_details->parentLevel,
            'parent_id' => $project_details->parentId,
            'is_creator_of_project' =>
                $project_details->createdBy == $userId ? 1 : 0,
            'child_projects' => $childProjectArray
        );
    }

    /**
     * Get notification list
     */
    public function getNotificationList()
    {
        try {
            if (!session('user_details')) {
                return $this->sendResultJSON('11', 'Unauthorised');
            }
            $user_id = session('user_details')->id;
            $notifications = NotificationDetail::where('sentTo', $user_id)
                ->orderBy('id', 'desc')
                ->get();
            $notification_array = array();
            foreach (
                count($notifications) > 0 ? $notifications : array()
                as $n
            ) {
                if (!$n->projectDetail) {
                    continue;
                }
                $project_data = $n->projectDetail;
                $parameters = json_decode($n->parameters, true);
                $notification_text = $n->notificationText;
                $data = array();
                $data['id'] = $n->id;
                $data['data_to_display'] = '';
                $data['type'] = '';
                $data['project_id'] = '';
                $data['project_name'] = '';
                $data['notification_type'] = $n->notificationType;
                $data['project_status'] = ($project_data->type == config("constants.type.project") ? $project_data->status : ($project_data->parentProject ? $project_data->parentProject->status : 1));
                if (
                    $n->notificationType == 'member_invitation_create_project'
                ) {
                    $data['type'] = 'project';
                    $data['project_id'] = $project_data->id;
                    $data['project_name'] = $project_data->name;
                    $notification_text = str_replace(
                        '{project_name}',
                        $project_data->name,
                        $notification_text
                    );
                    if ($n->memberData) {
                        $member_data = $n->memberData;
                        $notification_text = str_replace(
                            '{creator}',
                            $member_data->name,
                            $notification_text
                        );
                        $data['sent_by_avatar'] = getUserAvatar(
                            $member_data->avatar
                        );
                    }
                } elseif ($n->notificationType == 'create_project') {
                    $data['type'] = 'project';
                    $data['project_id'] = $project_data->id;
                    $data['project_name'] = $project_data->name;
                    $notification_text = str_replace(
                        '{project_name}',
                        $project_data->name,
                        $notification_text
                    );
                    $member_ids = explode(',', $parameters['members']);
                    $logged_in_user = 0;
                    if (in_array($user_id, $member_ids)) {
                        unset($member_ids[array_search($user_id, $member_ids)]);
                        $logged_in_user = 1;
                    }
                    $members = UserDetail::selectRaw(
                        'GROUP_CONCAT(name) as name,GROUP_CONCAT(avatar) as avatar'
                    )
                        ->whereIn('id', $member_ids)
                        ->first();
                    if ($members) {
                        $notification_text = str_replace(
                            '{members}',
                            $logged_in_user
                                ? 'You,' . $members->name
                                : $members->name,
                            $notification_text
                        );
                        $avatars = explode(',', $members->avatar);
                        $data['sent_by_avatar'] = getUserAvatar($avatars[0]);
                    }
                } elseif ($n->notificationType == 'add_comment') {
                    $data['type'] = 'comment';
                    $data['project_id'] = $project_data->id;
                    $data['project_name'] = $project_data->name;
                    $notification_text = str_replace(
                        '{name}',
                        $project_data->name,
                        $notification_text
                    );

                    $commentedBy_data = UserDetail::selectRaw('name,avatar')
                        ->where('id', $parameters['commented_by'])
                        ->first();
                    if ($commentedBy_data) {
                        $notification_text = str_replace(
                            '{commented_by}',
                            $commentedBy_data->name,
                            $notification_text
                        );
                        $data['sent_by_avatar'] = getUserAvatar(
                            $commentedBy_data->avatar
                        );
                    }
                    $comment = CommentDetail::select('comment')
                        ->where('id', $parameters['comment_id'])
                        ->first();
                    if ($comment) {
                        $data['data_to_display'] = $comment->comment;
                    }
                } elseif ($n->notificationType == 'create_task') {
                    $data['type'] = 'task';
                    $data['project_id'] = $project_data->parentId;
                    $parent_project_name = $project_data->parentProject
                        ? $project_data->parentProject->name
                        : '';
                    $data['project_name'] = $parent_project_name;
                    $notification_text = str_replace(
                        '{project_name}',
                        $parent_project_name,
                        $notification_text
                    );
                    $data['data_to_display'] = $project_data->name;

                    if ($n->memberData) {
                        $member_data = $n->memberData;
                        $notification_text = str_replace(
                            '{creator}',
                            $member_data->name,
                            $notification_text
                        );
                        $data['sent_by_avatar'] = getUserAvatar(
                            $member_data->avatar
                        );
                    }
                } elseif ($n->notificationType == 'member_removed') {
                    $data['type'] = 'project';
                    $data['project_id'] = $project_data->id;
                    $data['project_name'] = $project_data->name;
                    $notification_text = str_replace(
                        '{name}',
                        $project_data->name,
                        $notification_text
                    );

                    if ($n->memberData) {
                        $member_data = $n->memberData;
                        $notification_text = str_replace(
                            '{removed_by}',
                            $member_data->name,
                            $notification_text
                        );
                        $data['sent_by_avatar'] = getUserAvatar(
                            $member_data->avatar
                        );
                    }
                } elseif ($n->notificationType == 'member_removed_by') {
                    $data['type'] = 'project';
                    $data['project_id'] = $project_data->id;
                    $data['project_name'] = $project_data->name;
                    $notification_text = str_replace(
                        '{name}',
                        $project_data->name,
                        $notification_text
                    );

                    if ($n->memberData) {
                        $member_data = $n->memberData;
                        $notification_text = str_replace(
                            '{removed_by}',
                            $member_data->name,
                            $notification_text
                        );
                        $data['sent_by_avatar'] = getUserAvatar(
                            $member_data->avatar
                        );
                    }

                    $user_data = UserDetail::select('name')
                        ->where('id', $parameters['removed_member'])
                        ->first();
                    if ($user_data) {
                        $notification_text = str_replace(
                            '{user}',
                            $user_data->name,
                            $notification_text
                        );
                    }
                } elseif ($n->notificationType == 'complete_uncomplete') {
                    $data['type'] = 'task';
                    $data['project_id'] = $project_data->parentId;
                    $parent_project_name = $project_data->parentProject
                        ? $project_data->parentProject->name
                        : '';
                    $data['project_name'] = $parent_project_name;
                    $notification_text = str_replace('{action}', ($parameters['action'] == "completed" ? "completed" : "incompleted"), $notification_text);
                    $notification_text = str_replace('{project_name}', $parent_project_name, $notification_text);
                    $data['data_to_display'] = $project_data->name;

                    $data['notification_type'] = $parameters['action'];

                    if ($n->memberData) {
                        $member_data = $n->memberData;
                        $notification_text = str_replace('{user}', $member_data->name, $notification_text);
                        $data['sent_by_avatar'] = getUserAvatar($member_data->avatar);
                    }
                } elseif ($n->notificationType == 'complete_project') {
                    $data['type'] = 'project';
                    $data['project_id'] = $project_data->id;
                    $data['project_name'] = $project_data->name;
                    $data['data_to_display'] = $project_data->name;
                    $data['notification_type'] = "completed";

                    if ($n->memberData) {
                        $member_data = $n->memberData;
                        $notification_text = str_replace('{user}', $member_data->name, $notification_text);
                        $data['sent_by_avatar'] = getUserAvatar($member_data->avatar);
                    }
                }
                $data['notification_text'] = $notification_text;
                $data['is_read'] = $n->isRead;
                $data['sent_time'] = Carbon::parse(
                    $n->sentTime
                )->diffForHumans();
                array_push($notification_array, $data);
            }
            return $this->sendResultJSON('1', '', array(
                'notifications' => $notification_array
            ));
        } catch (\Exception $e) {
            return $this->sendResultJSON('0', $e->getMessage());
        }
    }

    /**
     * Get notification list
     */
    public function getNotificationCount()
    {
        return $this->sendResultJSON('1', '', array('count' => NotificationDetail::where('sentTo', session('user_details')->id)->where('isRead', 0)->count()));
    }

    /**
     * Search project/task
     */
    public function searchProjectTask(Request $request)
    {
        try {
            if (!session('user_details')) {
                return $this->sendResultJSON('11', 'Unauthorised');
            }
            $validator = Validator::make(
                $request->all(),
                ['search_data' => 'required'],
                ['search_data.required' => 'Please enter search string']
            );
            if ($validator->fails()) {
                return $this->sendResultJSON('2', $validator->errors()->first());
            }
            $result_data = array();
            $userId = session('user_details')->id;
            $type = $request->input("type") ?? "task";
            $search_string = strtolower($request->input("search_data"));
            if ($type == "task" || $type == "project") {
                $all_project_tasks = ProjectTaskDetail::join('member_details', 'project_task_details.id', '=', 'member_details.ptId')
                    ->selectRaw('project_task_details.*,member_details.memberId')
                    ->where('member_details.memberId', $userId)
                    ->where('project_task_details.type', config("constants.type")[$type])
                    ->whereRaw('(LOWER(name) like "%' . $search_string . '%" OR (DATE_FORMAT(`dueDate`,"%Y") like "%' . $search_string . '%" OR DATE_FORMAT(`dueDate`,"%m") like "%' . $search_string . '%" OR DATE_FORMAT(`dueDate`,"%d") like "%' . $search_string . '%") OR (DATE_FORMAT(STR_TO_DATE(`dueDateTime`,"%H:%i"),"%H") like "%' . $search_string . '%" OR DATE_FORMAT(STR_TO_DATE(`dueDateTime`,"%H:%i"),"%i") like "%' . $search_string . '%"))')
                    ->whereRaw('member_details.deleted_at is null')
                    ->orderBy('project_task_details.id', 'desc')
                    ->get();
                foreach (count($all_project_tasks) > 0 ? $all_project_tasks : array() as $pt) {
                    array_push($result_data, listMethodData($pt, $userId));
                }
            } else if ($type == "tags") {
                $tags = Tags::selectRaw('id,tagName')->where('userId', $userId)->whereRaw('LOWER(tagName) like "%' . $search_string . '%"')->orderBy('id', 'desc')->get();
                $tasks = $projects = $tag_project_ids = array();
                foreach (count($tags) > 0 ? $tags : array() as $t) {
                    $all_tags_pt = ProjectTaskDetail::join('member_details', 'project_task_details.id', '=', 'member_details.ptId')
                        ->selectRaw('project_task_details.*,member_details.memberId')
                        ->where('member_details.memberId', $userId)
                        ->whereRaw("FIND_IN_SET(" . $t->id . ",tags) > 0")
                        ->whereRaw('member_details.deleted_at is null')
                        ->orderBy('project_task_details.id', 'desc')
                        ->get();
                    foreach (count($all_tags_pt) > 0 ? $all_tags_pt : array() as $pt) {
                        if (!in_array($pt->id, $tag_project_ids)) {
                            array_push($tag_project_ids, $pt->id);
                            if ($pt->type == config("constants.type.project")) {
                                array_push($projects, listMethodData($pt, $userId));
                            } else if ($pt->type == config("constants.type.task")) {
                                array_push($tasks, listMethodData($pt, $userId));
                            }
                        }
                    }
                }
                $result_data = array_merge($tasks, $projects);
            } else if ($type == "comment") {
                $comment_details = CommentDetail::join('user_details', 'comment_details.commentedBy', '=', 'user_details.id')->selectRaw('comment_details.*,user_details.name')->whereRaw('(LOWER(comment_details.comment) like "%' . $search_string . '%" OR LOWER(user_details.name) like "%' . $search_string . '%" OR (DATE_FORMAT(`commentedTime`,"%Y") like "%' . $search_string . '%" OR DATE_FORMAT(`commentedTime`,"%m") like "%' . $search_string . '%" OR DATE_FORMAT(`commentedTime`,"%d") like "%' . $search_string . '%" OR DATE_FORMAT(`commentedTime`,"%H") like "%' . $search_string . '%" OR DATE_FORMAT(`commentedTime`,"%i") like "%' . $search_string . '%" OR DATE_FORMAT(`commentedTime`,"%s") like "%' . $search_string . '%"))')->whereRaw('comment_details.pt_id IN (SELECT ptId from member_details where memberId = ' . $userId . ')')->orderBy('id', 'desc')->get();
                foreach (count($comment_details) > 0 ? $comment_details : array() as $c) {
                    array_push($result_data, array(
                        'id' => $c->id,
                        'comment' => $c->comment,
                        'parentID' => $c->parentId,
                        'level' => $c->parentLevel,
                        'type' => ($c->projectDetail ? array_search($c->projectDetail->type, config('constants.type')) : ""),
                        'ptID' => $c->pt_id,
                        'ptName' => ($c->projectDetail ? $c->projectDetail->name : ""),
                        'parentProjectName' => ($c->projectDetail ? ($c->projectDetail->parentProject ? $c->projectDetail->parentProject->name : "") : ""),
                        'parentProjectColor' => ($c->projectDetail ? ($c->projectDetail->parentProject ? $c->projectDetail->parentProject->color : "") : ""),
                        'documentName' => $c->documentName,
                        'documentSize' => number_format($c->documentSize, 2),
                        'documentType' => $c->documentType != '' ? getDocumentType($c->documentType) : null,
                        'documentURL' => $c->documentName != '' ? (asset('uploads') . '/' . $c->pt_id . '/comment/' . $c->documentName) : null,
                        'documentThumbUrl' => $c->documentThumbUrl != '' ? (asset('uploads') . '/' . $c->pt_id . '/comment/thumbnail/' . $c->documentThumbUrl) : null,
                        'commentedByUserId' => $c->commentedBy,
                        'commentedBy' => $c->name,
                        'commentedTime' => convertCommentDate(session('user_details')->timezone, $c->commentedTime)
                    ));

                }
            }
            return $this->sendResultJSON('1', '', array(
                'data' => $result_data
            ));
        } catch (\Exception $e) {
            return $this->sendResultJSON('0', $e->getMessage());
        }
    }

    /**
     *  Method to get user associated member data
     *
     */
    public function associatedMemberList(Request $request)
    {
        try {
            if (!session('user_details')) {
                return $this->sendResultJSON('11', 'Unauthorised');
            }
            $user_id = session('user_details')->id;
            $projects = MemberDetail::select("ptId")->where("memberId", $user_id)->get();
            $project_ids = array();
            foreach (count($projects) > 0 ? $projects : array() as $p) {
                array_push($project_ids, $p->ptId);
            }
            $member_details = MemberDetail::whereIn("ptId", $project_ids)->where("memberId", "!=", $user_id)->orderBy("memberId", "asc")->get();
            $members = array();
            foreach (
                count($member_details) > 0 ? $member_details : array()
                as $m
            ) {
                if (isset($m->memberData)) {
                    $memberData = $m->memberData;
                    if (!isset($members[$memberData->id])) {
                        $members[$memberData->id] = array(
                            'id' => $m->memberData->id,
                            'name' => $m->memberData->name ?? $m->memberData->email,
                            'email' => $m->memberData->email
                        );
                    }
                }
            }
            return $this->sendResultJSON('1', '', array('members' => array_values($members)));
        } catch (\Exception $e) {
            return $this->sendResultJSON('0', $e->getMessage());
        }
    }

    /**
     * Method to get list of projects and tasks by tag
     */

    public function getProjectTaskByTag(Request $request)
    {
        try {
            if (!session('user_details')) {
                return $this->sendResultJSON('11', 'Unauthorised');
            }
            $validator = Validator::make($request->all(), ['tag_id' => 'required'], ['tag_id.required' => 'Please select tag']);
            if ($validator->fails()) {
                return $this->sendResultJSON('2', $validator->errors()->first());
            }
            $userId = session('user_details')->id;
            $tag_id = $request->input("tag_id");
            $is_completed = $request->input('is_completed') ?? 0;
            $completed_status = config('constants.project_status.completed');
            $all_tasks = ProjectTaskDetail::join('member_details', 'project_task_details.id', '=', 'member_details.ptId')
                ->selectRaw('project_task_details.*,member_details.memberId')
                ->whereRaw("FIND_IN_SET(" . $tag_id . ",tags) > 0");
            if (!$is_completed) {
                $all_tasks = $all_tasks->where('project_task_details.status', '!=', $completed_status);
            }
            $all_tasks = $all_tasks
                ->where('member_details.memberId', $userId)
                ->where('project_task_details.type', config('constants.type.task'))
                ->whereRaw('member_details.deleted_at is null')
                ->orderBy('project_task_details.id', 'desc')
                ->get();

            $tasks = array();
            $completed_tasks = array();
            foreach (count($all_tasks) > 0 ? $all_tasks : array() as $t) {
                $data = listMethodData($t, $userId);
                if ($t->status == $completed_status) {
                    array_push($completed_tasks, $data);
                } else {
                    array_push($tasks, $data);
                }
            }
            $tasks = array_merge($tasks, $completed_tasks);

            $is_completed_project = $request->input('is_completed_project') ?? 0;
            $is_review = $request->input('is_display_review') ?? 0;
            $projectTaskList = ProjectTaskDetail::join('member_details', 'project_task_details.id', '=', 'member_details.ptId')
                ->selectRaw('project_task_details.*,member_details.memberId')
                ->whereRaw("FIND_IN_SET(" . $tag_id . ",tags) > 0")
                ->where('member_details.memberId', $userId)
                ->where('project_task_details.type', config('constants.type.project'))
                ->whereRaw('member_details.deleted_at is null');
            if (!$is_completed_project) {
                $projectTaskList = $projectTaskList->where('project_task_details.status', '!=', $completed_status);
            }
            if (!$is_review) {
                $projectTaskList = $projectTaskList->where('project_task_details.status', '!=', config("constants.project_status.review"));
            }
            $projectTaskList = $projectTaskList->orderBy('project_task_details.id', 'desc')->get();
            $projects = array();
            $completed_projects = array();
            $review_projects = array();
            foreach (count($projectTaskList) > 0 ? $projectTaskList : array() as $p) {
                $data = listMethodData($p, $userId);
                if ($p->status == config("constants.project_status.completed")) {
                    array_push($completed_projects, $data);
                } else if ($p->status == config("constants.project_status.review")) {
                    array_push($review_projects, $data);
                } else {
                    array_push($projects, $data);
                }
            }
            $projects = array_merge($projects, $review_projects, $completed_projects);
            return $this->sendResultJSON('1', '', array(
                'tasks' => $tasks,
                'projects' => $projects
            ));
        } catch (\Exception $e) {
            return $this->sendResultJSON('0', $e->getMessage());
        }
    }
}
