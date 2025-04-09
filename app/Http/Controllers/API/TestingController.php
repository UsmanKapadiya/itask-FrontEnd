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
use App\Notifications\MailNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Validator;

class TestingController extends Controller
{
    /**
     *  Method to get project lists
     *
     */
    public function projectList($is_inner_call = false)
    {
        try {
            if (!$is_inner_call && !session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $userId = session("user_details")->id;
            $member_projects = MemberDetail::selectRaw("id,ptId")->where("memberId", $userId)->orderBy("id", "desc")->get();
            $projects = array();
            $type = config("constants.type.project");
            foreach (count($member_projects) > 0 ? $member_projects : array() as $m) {
                if (isset($m->projectDetail)) {
                    $firstLevel = $m->projectDetail->selectRaw("id,name,parentId,parentLevel,color,createdBy")->where("id", $m->ptId)->where("parentLevel", 1)->where("type", $type)->first();
                    if ($firstLevel) {
                        $secondLevel = $m->projectDetail->selectRaw("id,name,parentId,parentLevel,color,createdBy")->where("type", $type)->where("parentLevel", 2)->where("parentId", $firstLevel->id)->orderBy("id", "desc")->get();
                        $secondLevelChilds = array();
                        foreach (count($secondLevel) > 0 ? $secondLevel : array() as $s) {
                            $thirdLevel = $m->projectDetail->selectRaw("id,name,parentId,parentLevel,color,createdBy")->where("type", $type)->where("parentLevel", 3)->where("parentId", $s->id)->orderBy("id", "desc")->get();
                            $thirdLevelChilds = array();
                            foreach (count($thirdLevel) > 0 ? $thirdLevel : array() as $t) {
                                $fourthLevel = $m->projectDetail->selectRaw("id,name,parentId,parentLevel,color,createdBy")->where("type", $type)->where("parentLevel", 4)->where("parentId", $t->id)->orderBy("id", "desc")->get();
                                $fourthLevelChilds = array();
                                foreach (count($fourthLevel) > 0 ? $fourthLevel : array() as $f) {
                                    $fifthLevel = $m->projectDetail->selectRaw("id,name,parentId,parentLevel,color,createdBy")->where("type", $type)->where("parentLevel", 5)->where("parentId", $f->id)->orderBy("id", "desc")->get();
                                    $fifthLevelChilds = array();
                                    foreach (count($fifthLevel) > 0 ? $fifthLevel : array() as $ff) {
                                        array_push($fifthLevelChilds, array("id" => $ff->id, "name" => $ff->name, "color" => $ff->color, "level" => $ff->parentLevel, "parent_id" => $ff->parentId, "no_of_tasks" => $ff->taskTotal(), "is_creator_of_project" => ($ff->createdBy == $userId ? 1 : 0), "child_projects" => array()));
                                    }
                                    array_push($fourthLevelChilds, array("id" => $f->id, "name" => $f->name, "color" => $f->color, "level" => $f->parentLevel, "parent_id" => $f->parentId, "no_of_tasks" => $f->taskTotal(), "is_creator_of_project" => ($f->createdBy == $userId ? 1 : 0), "child_projects" => $fifthLevelChilds));
                                }
                                array_push($thirdLevelChilds, array("id" => $t->id, "name" => $t->name, "color" => $t->color, "level" => $t->parentLevel, "parent_id" => $t->parentId, "no_of_tasks" => $t->taskTotal(), "is_creator_of_project" => ($t->createdBy == $userId ? 1 : 0), "child_projects" => $fourthLevelChilds));
                            }
                            array_push($secondLevelChilds, array("id" => $s->id, "name" => $s->name, "color" => $s->color, "level" => $s->parentLevel, "parent_id" => $s->parentId, "no_of_tasks" => $s->taskTotal(), "is_creator_of_project" => ($s->createdBy == $userId ? 1 : 0), "child_projects" => $thirdLevelChilds));
                        }
                        array_push($projects, array("id" => $firstLevel->id, "name" => $firstLevel->name, "color" => $firstLevel->color, "level" => $firstLevel->parentLevel, "parent_id" => $firstLevel->parentId, "is_creator_of_project" => ($firstLevel->createdBy == $userId ? 1 : 0), "no_of_tasks" => $firstLevel->taskTotal(), "child_projects" => $secondLevelChilds));
                    }
                }
            }
            if (!$is_inner_call) {
                return $this->sendResultJSON("1", "", array("projects" => $projects));
            } else {
                return $projects;
            }
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method to get project and tag data
     *
     */
    public function projectTagList(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $permissions = config('permission.permissions');
            $all_permissions = array();
            foreach ($permissions as $key => $value) {
                $child_permissions = array();
                foreach ($value as $c => $v) {
                    $child_permissions[$c] = (($key == "task") ? (count($v) == 3 ? "all" : end($v)) : end($v));
                }
                $all_permissions[$key] = $child_permissions;
            }
            $user = session("user_details");
            $notification_count = NotificationDetail::where("sentTo", $user->id)->count();
            $user_notification_setting = NotificationSettingDetail::where("userId", $user->id)->get();
            $notification_settings = config("constants.notification_setting");
            $notification_setting_array = array();
            foreach ($notification_settings as $key => $n) {
                $notification_setting_array[$key] = array("email" => 1, "push_notification" => 1, "key_val" => $key, "title" => $n);
            }
            foreach (count($user_notification_setting) > 0 ? $user_notification_setting : array() as $un) {
                $notification_setting_array[$un->notificationType]["email"] = $un->email;
                $notification_setting_array[$un->notificationType]["push_notification"] = $un->pushNotification;
            }
            return $this->sendResultJSON("1", "", array("projects" => $this->projectList(true), "tags" => array(), "permissions" => $all_permissions, "notifications" => $notification_count, "notification_settings" => array_values($notification_setting_array), "timezones" => \DateTimeZone::listIdentifiers(), "default_reminder" => $user->automatic_reminder, "remind_via_email" => $user->remind_via_email, "remind_via_mobile_notification" => $user->remind_via_mobile_notification, "remind_via_desktop_notification" => $user->remind_via_desktop_notification));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     * Get projects for add sub project/add task
     */
    public function getProjectsForAdd(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $validator = Validator::make($request->all(), [
                "type" => "required"
            ], [
                "type.required" => "Please enter type"
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $type = $request->input("type");
            $permission = ($type == "project" ? config("permission.permissions.project.add_sub_project") : config("permission.permissions.project.add_task"));
            if (count($permission) == 1 && in_array("creator", $permission)) {
                $all_projects = $this->getCreatorProjects();
            } else {
                $all_projects = $this->projectList(true, $permission);
            }
            return $this->sendResultJSON("1", "", array("projects" => $all_projects));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    private function getCreatorProjects()
    {
        $projects = array();
        $userId = session("user_details")->id;
        $project_type = config("constants.type.project");
        $firstLevelProjects = ProjectTaskDetail::selectRaw("id,name,parentLevel,color,createdBy")->where("createdBy", $userId)->where("parentLevel", 1)->where("type", $project_type)->orderBy("id", "desc")->get();
        foreach (count($firstLevelProjects) > 0 ? $firstLevelProjects : array() as $firstLevel) {
            $secondLevel = ProjectTaskDetail::selectRaw("id,name,parentLevel,color,createdBy")->where("createdBy", $userId)->where("type", $project_type)->where("parentLevel", 2)->where("parentId", $firstLevel->id)->orderBy("id", "desc")->get();
            $secondLevelChilds = array();
            foreach (count($secondLevel) > 0 ? $secondLevel : array() as $s) {
                $thirdLevel = ProjectTaskDetail::selectRaw("id,name,parentLevel,color,createdBy")->where("createdBy", $userId)->where("type", $project_type)->where("parentLevel", 3)->where("parentId", $s->id)->orderBy("id", "desc")->get();
                $thirdLevelChilds = array();
                foreach (count($thirdLevel) > 0 ? $thirdLevel : array() as $t) {
                    $fourthLevel = ProjectTaskDetail::selectRaw("id,name,parentLevel,color,createdBy")->where("createdBy", $userId)->where("type", $project_type)->where("parentLevel", 4)->where("parentId", $t->id)->orderBy("id", "desc")->get();
                    $fourthLevelChilds = array();
                    foreach (count($fourthLevel) > 0 ? $fourthLevel : array() as $f) {
                        $fifthLevel = ProjectTaskDetail::selectRaw("id,name,parentLevel,color,createdBy")->where("createdBy", $userId)->where("type", $project_type)->where("parentLevel", 5)->where("parentId", $f->id)->orderBy("id", "desc")->get();
                        $fifthLevelChilds = array();
                        foreach (count($fifthLevel) > 0 ? $fifthLevel : array() as $ff) {
                            array_push($fifthLevelChilds, array("id" => $ff->id, "name" => $ff->name, "color" => $ff->color, "level" => $ff->parentLevel, "no_of_tasks" => $ff->taskTotal(), "is_creator_of_project" => ($ff->createdBy == $userId ? 1 : 0), "child_projects" => array()));
                        }
                        array_push($fourthLevelChilds, array("id" => $f->id, "name" => $f->name, "color" => $f->color, "level" => $f->parentLevel, "no_of_tasks" => $f->taskTotal(), "is_creator_of_project" => ($f->createdBy == $userId ? 1 : 0), "child_projects" => $fifthLevelChilds));
                    }
                    array_push($thirdLevelChilds, array("id" => $t->id, "name" => $t->name, "color" => $t->color, "level" => $t->parentLevel, "no_of_tasks" => $t->taskTotal(), "is_creator_of_project" => ($t->createdBy == $userId ? 1 : 0), "child_projects" => $fourthLevelChilds));
                }
                array_push($secondLevelChilds, array("id" => $s->id, "name" => $s->name, "color" => $s->color, "level" => $s->parentLevel, "no_of_tasks" => $s->taskTotal(), "is_creator_of_project" => ($s->createdBy == $userId ? 1 : 0), "child_projects" => $thirdLevelChilds));
            }
            array_push($projects, array("id" => $firstLevel->id, "name" => $firstLevel->name, "color" => $firstLevel->color, "level" => $firstLevel->parentLevel, "is_creator_of_project" => ($firstLevel->createdBy == $userId ? 1 : 0), "no_of_tasks" => $firstLevel->taskTotal(), "child_projects" => $secondLevelChilds));
        }
        return $projects;
    }

    public function getProjects()
    {
        try {
            $get_project_tasks = ProjectTaskDetail::where('dueDate', '>=', Carbon::now()->format("Y-m-d"))->orderBy("id", "desc")->get();
            foreach (count($get_project_tasks) > 0 ? $get_project_tasks : array() as $pt) {
                echo "project id :" . $pt->id . " name :" . $pt->name . "\n";
                $type = array_search($pt->type, config('constants.type'));
                $parent_project_name = ($pt->parentProject ? ($pt->parentProject->name) : "");
                $dueDateTime = ($pt->dueDateTime != "" ? $pt->dueDateTime : "00:00");
                $dueDate = Carbon::parse($pt->dueDate . " " . $dueDateTime);

                $project_due_text = config("notificationText.project_due");
                $project_due_text = str_replace(array("{name}", "{date}"), array($pt->name, Carbon::parse($pt->dueDate)->format("M j")), $project_due_text);
                $subject = (config('app.name') . " " . Carbon::now()->format("M j") . " (1 $type due)");

                $member_details = MemberDetail::where("ptId", $pt->id)->get();
                foreach (count($member_details) > 0 ? $member_details : array() as $m) {
                    $user_data = $m->memberData;
                    $is_send_notification = $is_send_email = 0;
                    if ($pt->reminder != "None") {
                        if (isset(config('reminder')[$pt->reminder])) {
                            $dueDate = $dueDate->sub(config('reminder')[$pt->reminder]);
                            echo "due date in if :" . $dueDate . " now :" . Carbon::now()->format("Y-m-d H:i:00") . "\n";
                            if ($dueDate->equalTo(Carbon::now()->format("Y-m-d H:i:00"))) {
                                if ($user_data->remind_via_mobile_notification)
                                    $is_send_notification = 1;
                                if ($user_data->remind_via_email)
                                    $is_send_email = 1;
                            }
                        }
                    } else if ($user_data->automatic_reminder != "No default reminder") {
                        if (isset(config('reminder')[$user_data->automatic_reminder])) {
                            echo "due date in if :" . $dueDate . " now :" . Carbon::now()->format("Y-m-d H:i:00") . "\n";
                            $dueDate = $dueDate->sub(config('reminder')[$user_data->automatic_reminder]);
                            if ($dueDate->equalTo(Carbon::now()->format("Y-m-d H:i:00"))) {
                                if ($user_data->remind_via_mobile_notification)
                                    $is_send_notification = 1;
                                if ($user_data->remind_via_email)
                                    $is_send_email = 1;
                            }
                        }
                    }
                    if ($is_send_notification && $user_data->deviceToken != "") {
                        $pass_parameter["type"] = "reminder";
                        $pass_parameter["subtype"] = $type;
                        if ($type == "project") {
                            $pass_parameter["project_id"] = $pt->id;
                            $pass_parameter["project_name"] = $pt->name;
                        } else {
                            $pass_parameter["task_id"] = $pt->id;
                            $pass_parameter["project_id"] = intval($pt->parentId);
                            $pass_parameter["project_name"] = $parent_project_name;
                        }
                        if ($user_data->type == intval(config('constants.device_type.ios'))) {
                            sendNotificationIOS(array($user_data->id => $user_data->deviceToken), $project_due_text, config("notificationText.project_due"), array(), "project_due", $pass_parameter, $pt->id);
                            echo "member id :" . $user_data->id . " is email :" . $is_send_email . " notification :" . $is_send_notification . "\n";
                        } else {
                            sendNotificationAndroid(array($user_data->id => $user_data->deviceToken), $project_due_text, config("notificationText.project_due"), array(), "project_due", $pass_parameter, $pt->id);
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function updateOrder()
    {
        try {
            $projects = ProjectTaskDetail::where("parentId", 0)->get();
            foreach (count($projects) > 0 ? $projects : array() as $p) {
                $p->ptOrder = 1;
                $p->save();
                $this->getChildProjects($p->id);
            }
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    private function getChildProjects($project_id)
    {
        $sub_project_details = ProjectTaskDetail::where('parentId', $project_id)->orderBy("id", "desc")->get();
        foreach (count($sub_project_details) > 0 ? $sub_project_details : array() as $sp) {
            $sp->ptOrder = findLastOrder($project_id);
            $sp->save();
            $this->getChildProjects($sp->id);
        }
    }

    public function updateInboxTasks()
    {
        try {
            $users = UserDetail::get();
            foreach (count($users) > 0 ? $users : array() as $u) {
                $order = 1;
                $projects = ProjectTaskDetail::where("parentId", 0)->where("type", config("constants.type.task"))->where("createdBy", $u->id)->orderBy("id", "desc")->get();
                foreach (count($projects) > 0 ? $projects : array() as $p) {
                    $p->ptOrder = $order++;
                    $p->save();
                }
            }

        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }
}
