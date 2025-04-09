<?php

namespace App\Http\Controllers;

use App\Models\CommentDetail;
use App\Models\DocumentDetail;
use App\Models\InvitationDetail;
use App\Models\NotificationDetail;
use App\Models\MemberDetail;
use App\Models\Tags;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\UserDetail;
use App\Models\ProjectTaskDetail;
use Validator;
use Illuminate\Support\Facades\Auth;

class ListController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     *  Method to get Inbox tasks listing
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

    private function getChildOfInboxTasks($status, $parent_id)
    {
        $taskList = ProjectTaskDetail::where('parentId', $parent_id)->where('type', config('constants.type.task'))->where('createdBy', Auth::user()->id);
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
            $single_task = $this->getTaskDetails($t);
            $single_task["children"] = $this->getRecursiveInboxTasks($status, $t->id, array());
            array_push($result, $single_task);
        }
        return $result;
    }


    /**
     * Method to get count of inbox tasks
     */
    public function inboxTasksCount()
    {
        try {
            $userId = Auth::user()->id;
            $data = projectTaskDetail::where('type', config('constants.type.task'))->where('parentId', 0)->where('createdBy', $userId)->where('status', '!=', config('constants.project_status.completed'))->get();
            $count = 0;
            foreach (count($data) > 0 ? $data : array() as $d) {
                $count = $count + 1 + getTaskTotal($d->id, $userId);
            }
            return json_encode(array("count" => $count));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     *  Method to get project lists for sidebar
     */

    public function projectList(Request $request)
    {
        try {
            $is_completed = $request->input('is_completed');
            $userId = Auth::user()->id;
            $member_projects = ProjectTaskDetail::join('member_details', 'project_task_details.id', '=', 'member_details.ptId')->selectRaw('project_task_details.*')->where('member_details.memberId', $userId)->whereRaw("member_details.deleted_at IS NULL")->where('project_task_details.type', config('constants.type.project'));
            if ($is_completed) {
                $member_projects = $member_projects->where('project_task_details.status', config('constants.project_status.completed'));
            } else {
                $member_projects = $member_projects->where('project_task_details.status', '!=', config('constants.project_status.completed'));
            }
            $member_projects = $member_projects->orderBy('project_task_details.id', 'desc')->get();
            $projects = array();
            $not_assigned_parent_projects = array();
            foreach (count($member_projects) > 0 ? $member_projects : array() as $m) {
                if ($m->status == config('constants.project_status.review') && $m->createdBy == $userId) {
                    continue;
                }
                if ($m->parentId == 0) {
                    array_push($projects, $this->getChildProjects($m, $is_completed));
                } else {
                    if ($m->parentData) {
                        if ($is_completed) {
                            if ($m->parentProject && $m->parentProject->status == config('constants.project_status.completed')) {
                                continue;
                            } else {
                                array_push($projects, $this->getChildProjects($m, 1));
                            }
                        }
                    } else {
                        $project_data = $this->getChildProjects($m, $is_completed);
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
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     *  Recursive Method to get sub project lists whoes parent is not assigned
     */

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

    /**
     *  Recursive Method to get sub project lists for sidebar
     */

    private function getChildProjects($project_details, $is_completed = 0)
    {
        $userId = Auth::user()->id;
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
                array_push($child_projects, $this->getChildProjects($c, $is_completed));
            }
        }
        $status = getProjectStatusWeb($project_details);
        return array(
            'id' => $project_details->id,
            'name' => $project_details->name,
            'color' => $project_details->color,
            'type' => array_search($project_details->type, config("constants.type")),
            'parent_id' => $project_details->parentId,
            'is_creator_of_project' => $project_details->createdBy == $userId ? 1 : 0,
            'status' => $status['status'],
            'is_overdue' => $status['is_overdue'],
            'child_projects' => $child_projects,
            'no_of_tasks' => $project_details->taskTotal($project_details->id)
        );
    }

    /**
     *  Method to get parent projects in Add project
     */

    public function parentProjects(Request $request)
    {
        try {
            $type = $request->input('type');
            $permission = $type == 'project' ? config('permission.permissions.project.add_sub_project') : config('permission.permissions.project.add_task');
            $all_projects = array();
            if (count($permission) == 1 && in_array('creator', $permission)) {
                $all_projects = $this->getCreatorProjects($request);
            } else {
                $all_projects = $this->projectList();
            }
            return json_encode(array("projects" => $all_projects, "members" => $this->projectMembers(), "auto_reminder" => Auth::user()->automatic_reminder));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     *  Method to get creator's project and subproject
     */

    private function getCreatorProjects($request)
    {
        $userId = Auth::user()->id;
        $project_type = config('constants.type.project');
        $projects = $child_ids = array();
        array_push($projects, array('value' => '0', 'title' => 'No parent', 'color' => '', 'children' => array()));
        $allProjects = ProjectTaskDetail::where('createdBy', $userId)
            ->where('type', $project_type)
            ->whereNotIn('status', array(
                config('constants.project_status.completed'),
                config('constants.project_status.review')
            ));
        if ($request->input("pt_id")) {
            $child_ids = $this->getChildOfProject($request->input("pt_id"), array());
            $allProjects = $allProjects->whereNotIn("id", $child_ids);
        }
        $allProjects = $allProjects->orderBy('id', 'desc')->get();
        $not_assigned_parent_projects = array();
        foreach (count($allProjects) > 0 ? $allProjects : array() as $p) {
            if ($p->parentId == 0) {
                array_push($projects, $this->getCreateChildProjects($request, $p, $p->parentLevel + 1, $child_ids));
            } elseif (!$p->parentData) {
                array_push($not_assigned_parent_projects, array(
                    'value' => (string)$p->id,
                    'title' => $p->name,
                    'color' => $p->color,
                    'children' => array(),
                ));
            }
        }
        $projects = array_merge($projects, $not_assigned_parent_projects);
        $project_status = config("constants.project_status");
        if ($request->input("pt_id")) {
            $project_data = ProjectTaskDetail::where("id", $request->input("pt_id"))->whereIn("status", array($project_status["review"], $project_status["completed"]))->first();
            if ($project_data && $project_data->parentProject) {
                $parent_project = $project_data->parentProject;
                if ($parent_project->status == $project_status["review"] || $parent_project->status == $project_status["completed"]) {
                    array_push($projects, array(
                        'value' => (string)$parent_project->id,
                        'title' => $parent_project->name,
                        'color' => $parent_project->color,
                        'children' => array(),
                    ));
                }
            }

        }
        return $projects;
    }

    private function getCreateChildProjects($request, $project_details, $level, $ignore_ids)
    {
        $userId = Auth::user()->id;
        $childProjects = ProjectTaskDetail::where('createdBy', $userId)
            ->where('parentLevel', $level)
            ->where('type', config('constants.type.project'))
            ->whereNotIn('status', array(
                config('constants.project_status.completed'),
                config('constants.project_status.review')
            ));
        if (count($ignore_ids) > 0) {
            $childProjects = $childProjects->whereNotIn("id", $ignore_ids);
        }
        $childProjects = $childProjects->where('parentId', $project_details->id)->orderBy('ptOrder', 'asc')->get();

        $childProjectArray = array();
        foreach (count($childProjects) > 0 ? $childProjects : array() as $c) {
            array_push($childProjectArray, $this->getCreateChildProjects($request, $c, $c->parentLevel + 1, $ignore_ids));
        }
        return array(
            'value' => (string)$project_details->id,
            'title' => $project_details->name,
            'color' => $project_details->color,
            'children' => $childProjectArray,
        );
    }

    private function getChildOfProject($project_id, $result_array)
    {
        $first_level_child = ProjectTaskDetail::where('parentId', $project_id)->where('type', config('constants.type.project'))->get();
        array_push($result_array, $project_id);
        foreach (count($first_level_child) > 0 ? $first_level_child : array() as $f) {
            $result_array = $this->getChildOfProject($f->id, $result_array);
        }
        return $result_array;
    }

    /**
     *  Members of particular project
     */

    public function membersList($project_id)
    {
        try {
            $members = array();
            if (intval($project_id) != 0 && $project_id != "") {
                $memberlist = MemberDetail::where("ptId", $project_id)->where('memberId', '!=', Auth::user()->id)->get();
                foreach ($memberlist as $m) {
                    $memberData = $m->memberData;
                    array_push($members, array('id' => $memberData->id, 'name' => $memberData->name, 'email' => $memberData->email, 'avatar' => getUserAvatar($memberData->avatar)));
                }
            }
            return json_encode($members);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     *  Get all members
     */

    public function projectMembers()
    {
        try {
            $memberlist = UserDetail::selectRaw('id,name,email,avatar')
                ->where('id', '!=', Auth::user()->id)
                ->where('isVerified', 1)
                ->get();
            $members = array();
            foreach ($memberlist as $m) {
                array_push($members, array('id' => $m->id, 'name' => $m->name, 'email' => $m->email, 'avatar' => getUserAvatar($m->avatar)));
            }
            return $members;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     *  Get user specific tags
     */

    public function memberTags()
    {
        try {
            $userId = Auth::user()->id;
            $taglist = Tags::selectRaw('id,tagName')
                ->where('userId', $userId)
                ->whereRaw('tagName != ""')
                ->orderBy('id', 'desc')
                ->get();
            $tags = array();
            foreach ($taglist as $t) {
                array_push($tags, array('id' => $t->id, 'name' => $t->tagName));
            }
            return json_encode($tags);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     *  Priority wise Project/task listing
     */

    public function listByFlag(Request $request)
    {
        try {
            $userId = Auth::user()->id;
            $priority = $request->input('priority');
            $status = $request->input('completed');
            $data = array();
            $completed_status = config('constants.project_status.completed');
            $tasks = ProjectTaskDetail::join('member_details', 'project_task_details.id', '=', 'member_details.ptId')
                ->selectRaw('project_task_details.*,member_details.memberId')
                ->where('project_task_details.flag', $priority);
            if (!$status) {
                $tasks = $tasks->where('project_task_details.status', '!=', $completed_status);
            }
            $tasks = $tasks
                ->where('member_details.memberId', $userId)
                ->where('project_task_details.type', config('constants.type.task'))
                ->whereRaw('member_details.deleted_at is null')
                ->orderBy('project_task_details.id', 'desc')
                ->get();
            $completed_tasks = array();
            foreach (count($tasks) > 0 ? $tasks : array() as $t) {
                if ($t->status == $completed_status) {
                    array_push($completed_tasks, $this->getTaskDetails($t));
                } else {
                    array_push($data, $this->getTaskDetails($t));
                }
            }
            $is_completed_project = $request->input('completedProject') ?? 0;
            $is_review = $request->input('reviewProject') ?? 0;
            $projects = ProjectTaskDetail::join('member_details', 'project_task_details.id', '=', 'member_details.ptId')
                ->selectRaw('project_task_details.*,member_details.memberId')
                ->where('project_task_details.flag', $priority)
                ->where('member_details.memberId', $userId)
                ->where('project_task_details.type', config('constants.type.project'))
                ->whereRaw('member_details.deleted_at is null');
            if (!$is_completed_project) {
                $projects = $projects->where('project_task_details.status', '!=', $completed_status);
            }
            if (!$is_review) {
                $projects = $projects->where('project_task_details.status', '!=', config("constants.project_status.review"));
            }
            $projects = $projects->orderBy('project_task_details.id', 'desc')->get();
            $completed_projects = array();
            $review_projects = array();
            foreach (count($projects) > 0 ? $projects : array() as $p) {
                $details = $this->getProjectDetails($p);
                if ($p->status == $completed_status) {
                    array_push($completed_projects, $details);
                } else if ($p->status == config("constants.project_status.review")) {
                    array_push($review_projects, $details);
                } else {
                    array_push($data, $details);
                }
            }
            $data = array_merge($data, $completed_tasks, $review_projects, $completed_projects);
            return json_encode($data);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     *  Method to get under review project lists
     *
     */

    public function reviewProjectList(Request $request)
    {
        try {
            $userId = Auth::user()->id;
            $projects = array();
            $project_details = ProjectTaskDetail::where('type', config('constants.type.project'))->where('status', '=', config('constants.project_status.review'))->where('createdBy', $userId)->orderBy('id', 'desc')->get();
            foreach ($project_details as $p) {
                array_push($projects, $this->getProjectDetails($p));
            }
            return json_encode($projects);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

    }

    /**
     *  Sub Project/task of particular project
     */

    public function projectDetailList(Request $request)
    {
        try {
            $id = $request->input('projectId');
            $project = ProjectTaskDetail::where('id', $id)->where('type', config("constants.type.project"))->first();
            if (!$project) {
                return json_encode(array('error' => 'Project not found'));
            }
            $userId = Auth::user()->id;
            $is_member = MemberDetail::where("memberId", $userId)->where("ptId", $id)->count();
            if ($is_member == 0) {
                return json_encode(array('error' => 'Access denied'));
            }

            $projectmembers = MemberDetail::where('ptId', $id)->count();
            $invitedmembers = InvitationDetail::where('ptId', $id)->where('status', config('constants.invitation_status.pending'))->count();
            $members = $projectmembers + $invitedmembers;
            $projectattachments = DocumentDetail::where('ptId', $id)->count();
            $projectcomments = CommentDetail::where('pt_id', $id)->count();

            $data = $projects_name = array();
            $project_status = config("constants.project_status");
            $taskList = $this->getChildOfTasks($request, $id, $project->status);
            $overdue_count = 0;
            $total_tasks_count = ProjectTaskDetail::join('member_details', 'project_task_details.id', '=', 'member_details.ptId')->where("project_task_details.parentId", $id)->where("member_details.memberId", $userId)->where("project_task_details.type", config("constants.type.task"))->whereRaw('member_details.deleted_at is null')->count();
            $active_tasks_count = ProjectTaskDetail::join('member_details', 'project_task_details.id', '=', 'member_details.ptId')->where("project_task_details.parentId", $id)->where("member_details.memberId", $userId)->where("project_task_details.type", config("constants.type.task"))->whereRaw('member_details.deleted_at is null')->where("project_task_details.status", "=", config("constants.project_status.active"))->count();
            foreach (count($taskList) > 0 ? $taskList : array() as $t) {
                $get_overdue_data = getProjectStatusWeb($t);
                $single_task = $this->getTaskDetails($t);
                $single_task["children"] = $this->getRecursiveTasks($request, $t->id, $project->status, $overdue_count, array());
                if ($get_overdue_data['is_overdue']) {
                    $overdue_count = $overdue_count + 1;
                }
                $data[$single_task["order"]] = $single_task;
            }
            $is_review = $request->input('reviewProject');
            if (!$is_review && array_search($project->status, $project_status) != "review" && array_search($project->status, $project_status) != "completed") {
                $is_review = 0;
            }
            $sub_projects = $this->getChildOfSubproject($request, $project, $project->status);
            foreach (count($sub_projects) > 0 ? $sub_projects : array() as $d) {
                if (!$is_review) {
                    if ($d->status == $project_status["review"] && $d->createdBy == $userId)
                        continue;
                }
                $details = $this->getProjectDetails($d);
                $details["children"] = $this->getRecursiveSubProjects($request, $d, $project->status, $overdue_count, $is_review, array());
                $data[$details["order"]] = $details;
            }
            ksort($data);
            $member_emails = getMemberNames($project->id, $project->createdBy, 0);
            $project->is_creator = $project->createdBy == $userId ? 1 : 0;
            $project->member_emails = $member_emails["member_emails"];
            return json_encode(array(
                'data' => array_values($data),
                'project' => $project,
                'members' => $members,
                'comments' => $projectcomments,
                'attachments' => $projectattachments,
                'remaining_tasks' => $active_tasks_count,
                'overdue' => $overdue_count,
                'total_tasks' => $total_tasks_count,
                'type' => array_search($project->type, config("constants.type")),
                'is_allow_completed' => ($project->status == $project_status["review"] && $project->createdBy == $userId) ? 1 : 0,
                'breadcrumbs' => array_reverse($this->getParentProjectName($project, $projects_name)),
                'can_drag' => (($project->status == $project_status["review"] || $project->status == $project_status["completed"]) ? 0 : 1)
            ));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    private function getChildOfTasks($request, $parent_id, $status)
    {
        $project_status = config("constants.project_status");
        $taskList = ProjectTaskDetail::whereHas("memberData")->where('parentId', $parent_id)->where('type', config('constants.type.task'));
        $is_completed = $request->input('completed');
        if (array_search($status, $project_status) != "review" && array_search($status, $project_status) != "completed" && !$is_completed) {
            $taskList = $taskList->where('status', '!=', $project_status["completed"]);
        }
        $taskList = $taskList->orderBy('ptOrder', 'asc')->get();
        return $taskList;
    }

    private function getChildOfSubproject($request, $project, $status)
    {
        $project_status = config("constants.project_status");
        $sub_projects = ProjectTaskDetail::whereHas("memberData")->where('parentId', $project->id)->where('type', config('constants.type.project'))->where('parentLevel', intval($project->parentLevel) + 1);
        $is_project_completed = $request->input('completedProject');
        if (!$is_project_completed && array_search($status, $project_status) != "review" && array_search($status, $project_status) != "completed") {
            $sub_projects = $sub_projects->where('status', '!=', $project_status["completed"]);
        }
        $sub_projects = $sub_projects->orderBy('ptOrder', 'asc')->get();
        return $sub_projects;
    }

    private function getRecursiveTasks($request, $id, $parent_status, $overdue_count, $result)
    {
        $taskList = $this->getChildOfTasks($request, $id, $parent_status);
        foreach (count($taskList) > 0 ? $taskList : array() as $t) {
            $get_overdue_data = getProjectStatusWeb($t);
            $single_task = $this->getTaskDetails($t);
            $single_task["children"] = $this->getRecursiveTasks($request, $t->id, $parent_status, $overdue_count, array());
            if ($get_overdue_data['is_overdue']) {
                $overdue_count = $overdue_count + 1;
            }
            array_push($result, $single_task);
        }
        return $result;
    }

    private function getRecursiveSubProjects($request, $project_data, $parent_status, $overdue_count, $is_review, $result)
    {
        $userId = Auth::user()->id;
        $project_status = config("constants.project_status");
        $taskList = $this->getChildOfTasks($request, $project_data->id, $parent_status);
        foreach (count($taskList) > 0 ? $taskList : array() as $t) {
            $get_overdue_data = getProjectStatusWeb($t);
            $single_task = $this->getTaskDetails($t);
            $single_task["children"] = $this->getRecursiveTasks($request, $t->id, $parent_status, $overdue_count, array());
            if ($get_overdue_data['is_overdue']) {
                $overdue_count = $overdue_count + 1;
            }
            $result[$single_task["order"]] = $single_task;
        }
        $sub_projects = $this->getChildOfSubproject($request, $project_data, $parent_status);
        foreach (count($sub_projects) > 0 ? $sub_projects : array() as $d) {
            if (!$is_review) {
                if ($d->status == $project_status["review"] && $d->createdBy == $userId)
                    continue;
            }
            $details = $this->getProjectDetails($d);
            $details["children"] = $this->getRecursiveSubProjects($request, $d, $parent_status, $overdue_count, $is_review, array());
            $result[$details["order"]] = $details;
        }
        ksort($result);
        return array_values($result);
    }

    private function getParentProjectName($project, $array)
    {
        if (isset($project->parentProject)) {
            array_push($array, array("id" => $project->parentProject->id, "name" => $project->parentProject->name));
            $array = $this->getParentProjectName($project->parentProject, $array);
        }
        return $array;
    }

    /**
     *  Manipulate task data
     */

    private function getTaskDetails($task)
    {
        $userId = Auth::user()->id;
        $project_status = config("constants.project_status");
        $editdate = getEditDueDateTime($task->dueDate, $task->dueDateTime);
        $date = getDueDateTime($task->dueDate, $task->dueDateTime);
        $attachmentsCount = DocumentDetail::where('ptId', $task->id)->count();
        $comments = CommentDetail::where('pt_id', $task->id)->count();
        $get_overdue_data = getProjectStatusWeb($task);
        $completed = 0;
        if ($task->status == $project_status["completed"]) {
            $completed = 1;
        }
        $first_member = ($task->creatorData ? strtoupper(substr($task->creatorData->name, 0, 1)) : "");
        $members = MemberDetail::where("ptId", $task->id)->where("memberId", "!=", $task->createdBy)->get();
        if (count($members) == 1) {
            $first_member = strtoupper(substr($members[0]->memberData->name, 0, 1));
        }
        $member_emails = getMemberNames($task->id, $task->createdBy, 1);
        return array('id' => $task->id, 'name' => $task->name, 'order' => $task->ptOrder, 'editdueDate' => $editdate, 'dueDate' => $date, 'type' => $task->type, 'repeat' => $task->repeat, 'reminder' => $task->reminder, 'priority' => $task->flag, 'attachments' => $attachmentsCount, 'comments' => $comments, 'tags' => $task->tags != '' ? explode(',', getTagsName($task->tags)) : '', 'is_task' => 1, 'status' => array_search($get_overdue_data['status'], config('constants.project_status')), 'is_overdue' => $get_overdue_data['is_overdue'], 'member_count' => count($members), 'member_name' => $first_member, 'parentId' => $task->parentId, 'parentProjectName' => ($task->parentProject ? $task->parentProject->name : ""), 'parentProjectColor' => ($task->parentProject ? $task->parentProject->color : ""), 'isAllowInComplete' => ($task->parentProject ? (in_array($task->parentProject->status, array(config("constants.project_status.completed"), config("constants.project_status.review"))) ? 0 : 1) : 1), 'creator' => $task->createdBy, 'userId' => $userId, 'completed' => $completed, 'member_emails' => $member_emails["member_emails"]);
    }

    /**
     *  Manipulate project data
     */

    private function getProjectDetails($p)
    {
        $date = getDueDateTime($p->dueDate, $p->dueDateTime);
        $attachmentsCount = DocumentDetail::where('ptId', $p->id)->count();
        $comments = CommentDetail::where('pt_id', $p->id)->count();
        $get_overdue_data = getProjectStatusWeb($p);
        $member_emails = getMemberNames($p->id, $p->createdBy, 0);
        $details = array('id' => $p->id, 'name' => $p->name, 'dueDate' => $date, 'priority' => $p->flag, 'order' => $p->ptOrder, 'type' => $p->type, 'repeat' => $p->repeat, 'attachments' => $attachmentsCount, 'comments' => $comments, 'tags' => $p->tags != '' ? explode(',', getTagsName($p->tags)) : '', 'parentId' => $p->parentId, 'parentProjectName' => ($p->parentProject ? $p->parentProject->name : ""), 'parentProjectColor' => ($p->parentProject ? $p->parentProject->color : ""), 'userId' => Auth::user()->id, 'creator' => $p->createdBy, 'is_task' => 0, 'status' => array_search($get_overdue_data['status'], config('constants.project_status')), 'is_overdue' => $get_overdue_data['is_overdue'], 'member_emails' => $member_emails["member_emails"]);
        return $details;
    }

    /**
     *  Method to get Assigned members list by projectid
     *
     */

    public function memberDetailList($id)
    {
        try {
            $userId = Auth::user()->id;
            $member_details = MemberDetail::where('ptId', $id)->get();
            $members = array();
            $existing_members = array();
            foreach (count($member_details) > 0 ? $member_details : array() as $m) {
                if ($m->memberData) {
                    $member_data = $m->memberData;
                    array_push($members, array('id' => $member_data->id, 'name' => $member_data->name ?? $member_data->email, 'email' => $member_data->email, 'avtar' => getUserAvatar($member_data->avatar)));
                    array_push($existing_members, $member_data->id);
                }
            }

            $invitedmembers = InvitationDetail::selectRaw('id,memberEmailID')->where('status', config('constants.invitation_status.pending'))->where('ptId', $id)->get();
            $invitedmember = array();
            foreach (count($invitedmembers) > 0 ? $invitedmembers : array() as $m) {
                array_push($invitedmember, array('id' => $m->id, 'email' => $m->memberEmailID, 'avtar' => url('images/icon_profile.png')));
            }

            $memberlist = UserDetail::select('id', 'name', 'email', 'avatar')->whereNotIn('id', $existing_members)->get();
            $unassigned_members = array();
            foreach ($memberlist as $m) {
                array_push($unassigned_members, array('id' => $m->id, 'name' => $m->name, 'email' => $m->email, 'avatar' => getUserAvatar($m->avatar)));
            }
            return json_encode(array('loginId' => $userId, 'data' => $members, 'invitedmember' => $invitedmember, 'unassigned_members' => $unassigned_members));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     *  Method to get Unassigned members list by projectid
     *
     */

    public function unassignMemberDetailList($id)
    {
        try {
            $ptid = $id;
            $existing_members = array();
            $get_project_members = MemberDetail::selectRaw("GROUP_CONCAT(memberId) as members")->where("ptId", $ptid)->where('member_details.deleted_at', '=', NULL)->first();
            if ($get_project_members) {
                $existing_members = explode(",", $get_project_members->members);
            }
            $memberlist = UserDetail::select('id', 'name', 'email', 'avatar')->whereNotIn('id', $existing_members)->get();
            $members = array();
            foreach ($memberlist as $m) {
                array_push($members, array('id' => $m->id, 'name' => $m->name, 'email' => $m->email, 'avatar' => getUserAvatar($m->avatar)));
            }
            return json_encode($members);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     *  Method to comment list by projectid
     *
     */

    public function commentDetailList($ptid)
    {
        try {
            $project = ProjectTaskDetail::where('id', $ptid)->first();
            if (!$project) {
                return json_encode(array('error' => 'Project/task not found', 'data' => array()));
            }
            $user_id = Auth::user()->id;
            $is_existing_member = MemberDetail::where('ptId', $ptid)->where('memberId', $user_id)->count();
            if ($project->parentId != 0) {
                $is_member = MemberDetail::where('ptId', $project->type == config('constants.type.project') ? $ptid : $project->parentId)
                    ->where('memberId', $user_id)
                    ->count();
                if ($is_member == 0) {
                    return json_encode(array('error' => 'Access denied', 'data' => array()));
                }
            } else {
                if ($is_existing_member == 0) {
                    return json_encode(array('error' => 'Access denied', 'data' => array()));
                }
            }
            $comment_details = CommentDetail::where('pt_id', $ptid)->where('parentId', 0)->orderBy('id', 'desc')->get();
            $comments = array();
            foreach (count($comment_details) > 0 ? $comment_details : array() as $c) {
                $comment = $this->iterateComment($c, $ptid);
                $child_comments = CommentDetail::where('pt_id', $ptid)->where('parentId', $c->id)->orderBy('id', 'desc')->get();
                foreach (count($child_comments) > 0 ? $child_comments : array() as $cc) {
                    array_push($comment["reply_comment"], $this->iterateComment($cc, $ptid));
                }
                array_push($comments, $comment);


            }
            return json_encode(array("data" => $comments, "error" => "", "is_creator" => $project->createdBy == $user_id ? 1 : 0, "is_member" => $is_existing_member ? 1 : 0));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Function to iterate comments
     */
    private function iterateComment($comment, $ptid)
    {
        $user_id = Auth::user()->id;
        return array(
            'id' => $comment->id,
            'comment' => $comment->comment,
            'parentID' => $comment->parentId,
            'level' => $comment->parentLevel,
            'documentName' => $comment->documentName,
            'documentSize' => $comment->documentSize,
            'documentType' => $comment->documentType != '' ? getDocumentType($comment->documentType) : null,
            'documentURL' => $comment->documentName != '' ? asset('uploads') . '/' . $ptid . '/comment/' . $comment->documentName : null,
            'documentThumbUrl' => $comment->documentThumbUrl != '' ? asset('uploads') . '/' . $ptid . '/comment/thumbnail/' . $comment->documentThumbUrl : null,
            'commentedByUserId' => $comment->commentedBy,
            'commentedBy' => ($comment->memberData ? $comment->memberData->name : ""),
            'commentedByAvtar' => getUserAvatar($comment->memberData ? $comment->memberData->avatar : ""),
            'commentedTime' => convertCommentDate(Auth::user()->timezone, $comment->commentedTime),
            'canDelete' => ($comment->commentedBy == $user_id ? 1 : 0),
            'reply_comment' => array()
        );
    }

    /**
     *  Method to attachment list by projectid
     *
     */

    public function attachmentDetailList($id)
    {
        try {
            if ($id == "") {
                return json_encode(array(
                    'data' => '',
                    'error_msg' => "Project/task not found"
                ));
            }
            $project_details = ProjectTaskDetail::where('id', $id)->first();
            if (!$project_details) {
                return json_encode(array(
                    'data' => '',
                    'error_msg' => "Project/task not found"
                ));
            }
            $is_member = MemberDetail::where('ptId', $id)->where('memberId', Auth::user()->id)->count();
            if (!$is_member) {
                return json_encode(array(
                    'data' => '',
                    'error_msg' => "Access denied"
                ));
            }
            $document_details = DocumentDetail::where("ptId", $id)->get();
            $documents = array();
            foreach (count($document_details) > 0 ? $document_details : array() as $d) {
                $url = asset('uploads') . '/' . $d->ptId . '/' . rawurlencode($d->formatted_name);
                $document_type = getDocumentType($d->type);
                $default_icon = asset("/images/document/icon_default.png");
                if ($document_type == "document") {
                    $extension = pathinfo($d->formatted_name, PATHINFO_EXTENSION);
                    $icon_name = file_exists(public_path("/images/document/icon_" . $extension . ".png")) ? asset("/images/document/icon_" . $extension . ".png") : $default_icon;
                } else {
                    $icon_name = file_exists(public_path('uploads') . '/' . $d->ptId . '/' . $d->formatted_name) ? ($document_type != "video" ? $url : (asset('uploads') . '/' . $d->ptId . '/thumbnail/' . $d->videoThumbUrl)) : $default_icon;
                }
                array_push($documents, array("id" => $d->id, "name" => splitDocumentName($d->original_name), "type" => $d->type, "size" => $d->size, "icon" => $icon_name, "url" => $url, "uploaded_by" => ($d->memberData ? $d->memberData->name : ""), "uploaded_time" => convertAttachmentDate(Auth::user()->timezone, $d->uploadedTime)));
            }
            return json_encode(array('data' => $documents, 'error_msg' => '', "is_creator" => ($project_details->createdBy == Auth::user()->id ? 1 : 0), "is_member" => $is_member ? 1 : 0));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Get notification count of logged in user
     */

    public function getNotificationCount()
    {
        return $notification_count = NotificationDetail::where('sentTo', Auth::user()->id)->where('isRead', 0)->count();
    }

    /**
     * Get members of task and parent project
     */

    public function memberDetail($id)
    {
        try {
            $task = ProjectTaskDetail::where('id', $id)->first();
            if (!$task) {
                return json_encode(array('error' => 'Task not found'));
            }
            $members = array();
            $all_members = array();
            $member_details = MemberDetail::where('ptId', $id)->where('memberId', '!=', $task->createdBy)->get();
            foreach (count($member_details) > 0 ? $member_details : array() as $m) {
                if ($m->memberData) {
                    $memberdata = $m->memberData;
                    array_push($members, $memberdata->email);
                    array_push($all_members, array(
                        'id' => $memberdata->id,
                        'name' => $memberdata->name ?? $memberdata->email,
                        'email' => $memberdata->email,
                        'avatar' => getUserAvatar($memberdata->avatar)
                    ));
                }
            }
            $parent_members = array();
            if ($task->parentId != 0) {
                $parent_member_details = MemberDetail::where('ptId', $task->parentId)->where('memberId', '!=', Auth::user()->id)->get();
                foreach (count($parent_member_details) > 0 ? $parent_member_details : array() as $m) {
                    if ($m->memberData) {
                        $memberdata = $m->memberData;
                        array_push($parent_members, array(
                            'id' => $memberdata->id,
                            'name' => $memberdata->name ?? $memberdata->email,
                            'email' => $memberdata->email,
                            'avatar' => getUserAvatar($memberdata->avatar)
                        ));
                    }
                }
            }
            return json_encode(array('members' => $members, 'parent_members' => $parent_members, 'error' => '', 'all_member_detail' => $all_members));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Search listing
     */

    public function searchList(Request $request)
    {
        $userId = Auth::user()->id;
        $searchkey = $request->input('searchKey');
        $searchkey = trim($searchkey);
        $projectTasklist = ProjectTaskDetail::join('member_details', 'project_task_details.id', '=', 'member_details.ptId')
            ->selectRaw('project_task_details.*,member_details.memberId')
            ->where('member_details.memberId', $userId)
            ->whereRaw('(LOWER(name) like "%' . $searchkey . '%" OR (DATE_FORMAT(`dueDate`,"%Y") like "%' . $searchkey . '%" OR DATE_FORMAT(`dueDate`,"%m") like "%' . $searchkey . '%" OR DATE_FORMAT(`dueDate`,"%d") like "%' . $searchkey . '%") OR (DATE_FORMAT(STR_TO_DATE(`dueDateTime`,"%H:%i"),"%H") like "%' . $searchkey . '%" OR DATE_FORMAT(STR_TO_DATE(`dueDateTime`,"%H:%i"),"%i") like "%' . $searchkey . '%"))')
            ->whereRaw('member_details.deleted_at is null')
            ->orderBy('project_task_details.id', 'desc')
            ->get();
        $projects = $tasks = array();
        foreach (count($projectTasklist) > 0 ? $projectTasklist : array() as $pt) {
            if ($pt->type == config("constants.type.project")) {
                array_push($projects, $this->getProjectDetails($pt));
            } else if ($pt->type == config("constants.type.task")) {
                array_push($tasks, $this->getTaskDetails($pt));
            }
        }
        $taglist = Tags::selectRaw('id,tagName')->where('userId', $userId)->whereRaw('LOWER(tagName) like "%' . $searchkey . '%"')->orderBy('id', 'desc')->get();
        $t_projects = $t_tasks = array();
        foreach (count($taglist) > 0 ? $taglist : array() as $t) {
            $all_tags_pt = ProjectTaskDetail::join('member_details', 'project_task_details.id', '=', 'member_details.ptId')
                ->selectRaw('project_task_details.*,member_details.memberId')
                ->where('member_details.memberId', $userId)
                ->whereRaw("FIND_IN_SET(" . $t->id . ",tags) > 0")
                ->whereRaw('member_details.deleted_at is null')
                ->orderBy('project_task_details.id', 'desc')
                ->get();
            foreach (count($all_tags_pt) > 0 ? $all_tags_pt : array() as $pt) {
                if ($pt->type == config("constants.type.project")) {
                    array_push($t_projects, $this->getProjectDetails($pt));
                } else if ($pt->type == config("constants.type.task")) {
                    array_push($t_tasks, $this->getTaskDetails($pt));
                }
            }
        }
        $tags = array_merge($t_tasks, $t_projects);
        $commentlist = CommentDetail::join('user_details', 'comment_details.commentedBy', '=', 'user_details.id')->selectRaw('comment_details.*,user_details.name')->whereRaw('(LOWER(comment_details.comment) like "%' . $searchkey . '%" OR LOWER(user_details.name) like "%' . $searchkey . '%" OR (DATE_FORMAT(`commentedTime`,"%Y") like "%' . $searchkey . '%" OR DATE_FORMAT(`commentedTime`,"%m") like "%' . $searchkey . '%" OR DATE_FORMAT(`commentedTime`,"%d") like "%' . $searchkey . '%" OR DATE_FORMAT(`commentedTime`,"%H") like "%' . $searchkey . '%" OR DATE_FORMAT(`commentedTime`,"%i") like "%' . $searchkey . '%" OR DATE_FORMAT(`commentedTime`,"%s") like "%' . $searchkey . '%"))')->whereRaw('comment_details.pt_id IN (SELECT ptId from member_details where memberId = ' . $userId . ')')->orderBy('id', 'desc')->get();
        $comments = array();
        foreach (count($commentlist) > 0 ? $commentlist : array() as $c) {
            array_push($comments, array(
                'id' => $c->id,
                'comment' => $c->comment,
                'parentID' => $c->parentId,
                'level' => $c->parentLevel,
                'type' => ($c->projectDetail ? array_search($c->projectDetail->type, config('constants.type')) : ""),
                'ptName' => ($c->projectDetail ? $c->projectDetail->name : ""),
                'parentProjectName' => ($c->projectDetail ? ($c->projectDetail->parentProject ? $c->projectDetail->parentProject->name : "") : ""),
                'parentProjectColor' => ($c->projectDetail ? ($c->projectDetail->parentProject ? $c->projectDetail->parentProject->color : "") : ""),
                'commentedByUserId' => $c->commentedBy,
                'commentedBy' => $c->name,
                'commentedTime' => convertCommentDate(Auth::user()->timezone, $c->commentedTime),
                'ptID' => intval(($c->projectDetail && $c->projectDetail->type == config('constants.type.task')) ? $c->projectDetail->parentId : $c->pt_id),
            ));

        }
        return json_encode(array('projects' => $projects, 'tasks' => $tasks, 'tags' => $tags, 'comments' => $comments));
    }

    /**
     * Notification listing
     */

    public function notificationList()
    {
        try {
            $userId = Auth::user()->id;
            $notifications = NotificationDetail::where('sentTo', $userId)->orderBy('id', 'desc')->get();
            $notification_array = array();
            foreach (count($notifications) > 0 ? $notifications : array() as $n) {
                if (!$n->projectDetail) {
                    continue;
                }
                $project_data = $n->projectDetail;
                $parameters = json_decode($n->parameters, true);
                $notification_text = $n->notificationText;
                $data = array();
                $data['id'] = $n->id;
                $data['data_to_display'] = '';
                $data['data'] = array("type" => "", "pt_id" => $n->ptId, "display_data" => "", "slient_msg" => "");
                if ($n->notificationType == 'member_invitation_create_project') {
                    $notification_text = str_replace('{project_name}', $project_data->name, $notification_text);
                    if ($n->memberData) {
                        $member_data = $n->memberData;
                        $notification_text = str_replace('{creator}', $member_data->name, $notification_text);
                        $data['sent_by_avatar'] = getUserAvatar($member_data->avatar);
                    }
                    $data["data"]["type"] = "add_project";
                } elseif ($n->notificationType == 'create_project') {
                    $notification_text = str_replace('{project_name}', $project_data->name, $notification_text);
                    $member_ids = explode(',', $parameters['members']);
                    $logged_in_user = 0;
                    if (in_array($userId, $member_ids)) {
                        unset($member_ids[array_search($userId, $member_ids)]);
                        $logged_in_user = 1;
                    }
                    $members = UserDetail::selectRaw('GROUP_CONCAT(name) as name,GROUP_CONCAT(avatar) as avatar')->whereIn('id', $member_ids)->first();
                    if ($members) {
                        $notification_text = str_replace('{members}', $logged_in_user ? 'You,' . $members->name : $members->name, $notification_text);
                        $avatars = explode(',', $members->avatar);
                        $data['sent_by_avatar'] = getUserAvatar($avatars[0]);
                    }
                    $data["data"]["type"] = "add_project";
                } elseif ($n->notificationType == 'add_comment') {
                    $notification_text = str_replace('{name}', $project_data->name, $notification_text);

                    $commentedBy_data = UserDetail::selectRaw('name,avatar')->where('id', $parameters['commented_by'])->first();
                    if ($commentedBy_data) {
                        $notification_text = str_replace('{commented_by}', $commentedBy_data->name, $notification_text);
                        $data['sent_by_avatar'] = getUserAvatar($commentedBy_data->avatar);
                    }
                    $comment = CommentDetail::select('comment')->where('id', $parameters['comment_id'])->first();
                    if ($comment) {
                        $data['data_to_display'] = $comment->comment;
                    }
                    $project_id = ($project_data->type == config("constants.type.project") ? $project_data->id : $project_data->parentId);
                    $project_name = $project_data->name;
                    $data["data"]["pt_id"] = $project_id;
                    $data["data"]["display_data"] = array("project_id" => $project_data->id, "project_name" => $project_name);
                    $data["data"]["type"] = "add_comment";
                } elseif ($n->notificationType == 'create_task') {
                    $parent_project_name = $project_data->parentProject ? $project_data->parentProject->name : '';
                    $notification_text = str_replace('{project_name}', $parent_project_name, $notification_text);
                    $data['data_to_display'] = $project_data->name;

                    if ($n->memberData) {
                        $member_data = $n->memberData;
                        $notification_text = str_replace('{creator}', $member_data->name, $notification_text);
                        $data['sent_by_avatar'] = getUserAvatar($member_data->avatar);
                    }
                    $data["data"]["pt_id"] = $project_data->parentId;
                    $data["data"]["type"] = "add_task";
                } elseif ($n->notificationType == 'member_removed') {
                    $notification_text = str_replace('{name}', $project_data->name, $notification_text);

                    if ($n->memberData) {
                        $member_data = $n->memberData;
                        $notification_text = str_replace('{removed_by}', $member_data->name, $notification_text);
                        $data['sent_by_avatar'] = getUserAvatar($member_data->avatar);
                    }
                    $data["data"]["slient_msg"] = $notification_text;
                    $data["data"]["type"] = "member_removed";
                } elseif ($n->notificationType == 'member_removed_by') {
                    $notification_text = str_replace('{name}', $project_data->name, $notification_text);

                    if ($n->memberData) {
                        $member_data = $n->memberData;
                        $notification_text = str_replace('{removed_by}', $member_data->name, $notification_text);
                        $data['sent_by_avatar'] = getUserAvatar($member_data->avatar);
                    }
                    $user_data = UserDetail::select('name')->where('id', $parameters['removed_member'])->first();
                    if ($user_data) {
                        $notification_text = str_replace('{user}', $user_data->name, $notification_text);
                    }
                    $r_project_id = ($project_data->type == config("constants.type.project") ? $project_data->id : $project_data->parentId);
                    $data["data"]["pt_id"] = $r_project_id;
                    $data["data"]["type"] = "member_removed_by";
                } elseif ($n->notificationType == 'complete_uncomplete') {
                    $parent_project_name = $project_data->parentProject ? $project_data->parentProject->name : '';
                    $notification_text = str_replace('{action}', $parameters['action'], $notification_text);
                    $notification_text = str_replace('{project_name}', $parent_project_name, $notification_text);
                    $data['data_to_display'] = $project_data->name;

                    if ($n->memberData) {
                        $member_data = $n->memberData;
                        $notification_text = str_replace('{user}', $member_data->name, $notification_text);
                        $data['sent_by_avatar'] = getUserAvatar($member_data->avatar);
                    }
                    $data["data"]["pt_id"] = $project_data->parentId;
                    $data["data"]["type"] = "complete_uncomplete";
                } elseif ($n->notificationType == 'complete_project') {
                    $data['data_to_display'] = $project_data->name;

                    if ($n->memberData) {
                        $member_data = $n->memberData;
                        $notification_text = str_replace('{user}', $member_data->name, $notification_text);
                        $data['sent_by_avatar'] = getUserAvatar($member_data->avatar);
                    }
                    $data["data"]["type"] = "complete_project";
                }
                $data['notification_text'] = $notification_text;
                $data['is_read'] = $n->isRead;
                $data['sent_time'] = Carbon::parse($n->sentTime)->diffForHumans();
                array_push($notification_array, $data);
            }
            return json_encode($notification_array);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

    }

    /**
     *  Method to get list of projects and tasks by tag
     */

    public function getProjectTaskByTag(Request $request)
    {
        try {
            $userId = Auth::user()->id;
            $validator = Validator::make($request->all(), ['tag_id' => 'required'], ['tag_id.required' => 'Please select tag']);
            if ($validator->fails()) {
                return json_encode(array('error' => $validator->errors()->first(), 'response' => '', 'name' => ''));
            }
            $tag_id = $request->input('tag_id');
            $tags = Tags::where("id", $tag_id)->where("userId", $userId)->first();
            if (!$tags) {
                return json_encode(array('error' => "Tag not found", 'response' => '', 'name' => ''));
            }
            $status = $request->input('completed');
            $data = array();
            $completed_status = config('constants.project_status.completed');
            $tasks = ProjectTaskDetail::join('member_details', 'project_task_details.id', '=', 'member_details.ptId')
                ->selectRaw('project_task_details.*,member_details.memberId')
                ->whereRaw("FIND_IN_SET(" . $tag_id . ",tags) > 0");
            if (!$status) {
                $tasks = $tasks->where('project_task_details.status', '!=', $completed_status);
            }
            $tasks = $tasks
                ->where('member_details.memberId', $userId)
                ->where('project_task_details.type', config('constants.type.task'))
                ->whereRaw('member_details.deleted_at is null')
                ->orderBy('project_task_details.id', 'desc')
                ->get();
            $completed_tasks = array();
            foreach (count($tasks) > 0 ? $tasks : array() as $t) {
                if ($t->status == $completed_status) {
                    array_push($completed_tasks, $this->getTaskDetails($t));
                } else {
                    array_push($data, $this->getTaskDetails($t));
                }
            }
            $is_completed_project = $request->input('completedProject') ?? 0;
            $is_review = $request->input('reviewProject') ?? 0;
            $projects = ProjectTaskDetail::join('member_details', 'project_task_details.id', '=', 'member_details.ptId')
                ->selectRaw('project_task_details.*,member_details.memberId')
                ->whereRaw("FIND_IN_SET(" . $tag_id . ",tags) > 0")
                ->where('member_details.memberId', $userId)
                ->where('project_task_details.type', config('constants.type.project'))
                ->whereRaw('member_details.deleted_at is null');
            if (!$is_completed_project) {
                $projects = $projects->where('project_task_details.status', '!=', $completed_status);
            }
            if (!$is_review) {
                $projects = $projects->where('project_task_details.status', '!=', config("constants.project_status.review"));
            }
            $projects = $projects->orderBy('project_task_details.id', 'desc')->get();
            $completed_projects = array();
            $review_projects = array();
            foreach (count($projects) > 0 ? $projects : array() as $p) {
                $details = $this->getProjectDetails($p);
                if ($p->status == $completed_status) {
                    array_push($completed_projects, $details);
                } else if ($p->status == config("constants.project_status.review")) {
                    array_push($review_projects, $details);
                } else {
                    array_push($data, $details);
                }
            }
            $data = array_merge($data, $completed_tasks, $review_projects, $completed_projects);
            return json_encode(array('error' => '', 'response' => $data, 'name' => $tags->tagName));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}
