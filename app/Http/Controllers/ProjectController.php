<?php

namespace App\Http\Controllers;

use App\Events\ProjectEvent;
use App\Models\NotificationSettingDetail;
use App\Models\QueuedNotificationDetail;
use App\Models\StatusLogDetail;
use App\Models\UsersTokenDetail;
use App\Notifications\MailNotification;
use Carbon\Carbon;
use App\Models\CommentDetail;
use App\Models\DocumentDetail;
use App\Models\MemberDetail;
use App\Models\InvitationDetail;
use App\Models\ProjectTaskDetail;
use App\Models\NotificationDetail;
use App\Models\Tags;
use App\Models\UserDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Validator;
use Notification;

class ProjectController extends Controller
{
    /**
     * Method to create project/task
     */

    public function addProjectTask(Request $request)
    {
        try {
            $element_array = array(
                'name' => 'required'
            );
            $validator = Validator::make($request->all(), $element_array, [
                'name.required' => 'Please enter name'
            ]);
            if ($validator->fails()) {
                return json_encode(array(
                    'response' => '',
                    'error_msg' => $validator->errors()
                ));
            }

            $userId = Auth::user()->id;
            $project = new ProjectTaskDetail();
            $project->name = $request->input('name');
            if ($request->input('date')) {
                $datetime = Carbon::parse($request->input('date'));
                $project->dueDate = $datetime->format('Y-m-d');
                $project->dueDateTime = $datetime->format('H:i');
            }
            $project->repeat = $request->input('repeat') ?? "Never";
            $project->reminder = $request->input('reminder') ?? "None";
            $project->flag = $request->input('flag') ?? 1;
            if ($request->input('color'))
                $project->color = $request->input('color');
            if ($request->input("note"))
                $project->note = $request->input("note");

            $types = config("constants.type");
            $type = $request->input("type");
            $project->type = $types[$type];
            $project->parentId = 0;
            $project->parentLevel = 1;

            $parent_project_name = "";
            if ($request->input('parentproject')) {
                $parentproject = $request->input('parentproject');
                $parentLevel = ProjectTaskDetail::selectRaw('name,parentLevel')->where('id', $parentproject)->first();
                if ($parentLevel) {
                    $parent_project_name = $parentLevel->name;
                    if ($type == "project") {
                        $project->parentLevel = $parentLevel->parentLevel + 1;
                    }
                    $project->parentId = $parentproject;
                }
            }
            refreshOrder($project->parentId, 1, $project->type, $userId);
            $project->ptOrder = 1;
            $project->tags = $this->tagGeneration($request->input('tags'));
            $project->status = $request->input('status') ?? 1;
            $project->createdBy = $userId;
            $project->updatedBy = $userId;
            $project->save();
            $project_id = $project->id;

            //Create project folder
            $destination_path = public_path("uploads/$project_id");
            if (!File::exists($destination_path)) {
                File::makeDirectory($destination_path);
            }

            $member_detail = new MemberDetail();
            $member_detail->ptId = $project_id;
            $member_detail->memberId = $userId;
            $member_detail->save();

            $member_list = json_decode($request->input('members'));
            if ($member_list != null) {
                foreach (count($member_list) > 0 ? $member_list : array() as $m) {
                    if ($m == Auth::user()->email) {
                        continue;
                    }
                    $existingmember = UserDetail::where('email', $m)->first();
                    if (!$existingmember && ($type == "project")) {
                        $invitation = new InvitationDetail();
                        $invitation->ptId = $project_id;
                        $project_name = $project->name;
                        $invitation->memberId = 0;
                        $invitation->memberEmailID = $m;
                        $invitation->sentTime = Carbon::now();
                        $invitation->sentBy = $userId;
                        $invitation->status = config('constants.invitation_status')['pending'];
                        $invitation->save();
                        $due_date = getDueDateTime($project->dueDate, $project->dueDateTime);
                        $mail_text = ($project->type == config('constants.type.project') ? (Auth::user()->name . "  assigned a Project to you") : (Auth::user()->name . " assigned a Task to you"));
                        Notification::send($invitation, new MailNotification(array('text' => $mail_text, 'subtext' => ($project_name . ($parent_project_name != "" ? " . " . $parent_project_name : "")), 'dueDateText' => ($due_date != "" ? "Due Date : " . $due_date : ""), 'btntext' => 'View this project', 'subject' => ($project_name . " | Added you"))));
                    } else {
                        $member = new MemberDetail();
                        $member->ptId = $project_id;
                        $member->memberId = $existingmember->id;
                        $member->save();
                    }
                }
            }

            if ($request->input('comment')) {
                //Add Comment
                $comment = new CommentDetail();
                $comment->pt_id = $project->id;
                $comment->comment = $request->input('comment');
                $comment->parentId = 0;
                $comment->commentedBy = $userId;
                $comment->commentedTime = Carbon::now();
                $comment->save();
            }

            $file = $request->file('files');
            $this->uploadDocuments($file, $project_id);

            $notification = new QueuedNotificationDetail();
            $notification->notification_type = (($type == "project") ? "add_member" : "task_assigned");
            $notification->pt_id = $project_id;
            $notification->created_by = $userId;
            $notification->save();
            //$this->sendNotificationProjectTask($project, $notification_array, $email_array, $ios_device_tokens, $android_device_tokens, $remaining_user_tokens, $remaining_users);
            //sendReorderNotification($project->parentId, $project, 0);
            return json_encode(array(
                'response' => (ucfirst($request->input("type")) . ' Added'),
                'error_msg' => ''
            ));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Function to send notification after create project/task
     */
    public function sendCreateNotification()
    {
        $data = QueuedNotificationDetail::orderBy("id", "asc")->first();
        if ($data) {
            if ($data->notification_type == "add_member" || $data->notification_type == "task_assigned") {
                $email_array = $notification_array = $ios_device_tokens = $android_device_tokens = $remaining_users = $remaining_user_tokens = array();
                $members = MemberDetail::where("ptId", $data->pt_id)->get();
                foreach (!$members->isEmpty() ? $members : array() as $m) {
                    $user_data = $m->memberData;
                    if ($data->created_by == $m->memberId) {
                        array_push($remaining_users, $data->created_by);
                        if ($user_data->deviceToken != "" && $user_data->type == intval(config('constants.device_type.ios'))) {
                            $remaining_user_tokens[$data->created_by] = $user_data->deviceToken;
                        }
                        continue;
                    }
                    $permission = array("add_member" => array("email" => 1, "push_notification" => 1), "task_assigned" => array("email" => 1, "push_notification" => 1));
                    $member_permission = NotificationSettingDetail::where("userId", $m->memberId)->whereIn("notificationType", array("add_member", "task_assigned"))->get();
                    foreach (count($member_permission) > 0 ? $member_permission : array() as $mp) {
                        $permission[$mp->notificationType]["email"] = $mp->email;
                        $permission[$mp->notificationType]["push_notification"] = $mp->pushNotification;
                    }

                    if ($permission[$data->notification_type]["push_notification"]) {
                        if ($user_data->deviceToken != "") {
                            if ($user_data->type == intval(config('constants.device_type.ios'))) {
                                $ios_device_tokens[$user_data->id] = $user_data->deviceToken;
                            } else {
                                $android_device_tokens[$user_data->id] = $user_data->deviceToken;
                            }
                        }
                        array_push($notification_array, $user_data->id);
                    } else {
                        if ($user_data->deviceToken != "" && $user_data->type == intval(config('constants.device_type.ios'))) {
                            $remaining_user_tokens[$user_data->id] = $user_data->deviceToken;
                        }
                        array_push($remaining_users, $user_data->id);
                    }
                    $members[$m->memberId] = $user_data->name;
                    if ($permission[$data->notification_type]["email"]) {
                        $email_array[$m->memberId] = $user_data;
                    }
                }
                $this->sendNotificationProjectTask($data->projectDetail, $notification_array, $email_array, $ios_device_tokens, $android_device_tokens, $remaining_user_tokens, $remaining_users);
                sendReorderNotification($data->projectDetail->parentId, $data->projectDetail, 0);
                $data->delete();
            }
        }
    }

    /**
     * Send notification when assign members
     */

    public function sendNotificationProjectTask($project_detail, $notification_members, $email_members, $ios_device_tokens, $android_device_tokens, $remaining_user_tokens, $remaining_users)
    {
        $project_name = $project_detail->name;
        $ptid = $project_detail->id;
        $due_date = getDueDateTime($project_detail->dueDate, $project_detail->dueDateTime);
        $parent_ids = getBaseParentId($project_detail, array(), 1);
        $creator_data = $project_detail->creatorData;
        if ($project_detail->type == config('constants.type.project')) {
            $pass_parameter["type"] = "assign";
            $pass_parameter["subtype"] = "project";
            $pass_parameter["project_id"] = $project_detail->id;
            $pass_parameter["project_name"] = $project_name;
            $pass_parameter["project_status"] = $project_detail->status;
            $pass_parameter["parent_id"] = intval($project_detail->parentId);
            $pass_parameter["all_parent_ids"] = $parent_ids;
            $pass_parameter["all_child_ids"] = getChildIds($project_detail->id, array(), 1);

            $member_invitation_text = config("notificationText.member_invitation_create_project");
            $member_invitation_text = str_replace(array("{creator}", "{project_name}"), array($creator_data->name, $project_name), $member_invitation_text);

            $mail_text = $creator_data->name . " assigned a Project to you";
            foreach (count($email_members) > 0 ? $email_members : array() as $key => $value) {
                Notification::send($value, new MailNotification(array('text' => $mail_text, 'subtext' => $project_name, 'dueDateText' => ($due_date != "" ? "Due Date : " . $due_date : ""), 'btntext' => 'View this project', 'subject' => ($project_name . " | Added you"))));
            }
            if (count($ios_device_tokens) > 0) {
                sendNotificationIOS($ios_device_tokens, $member_invitation_text, config("notificationText.member_invitation_create_project"), array(), "member_invitation_create_project", $pass_parameter, $ptid);
            }
            if (count($android_device_tokens) > 0) {
                sendNotificationAndroid($android_device_tokens, $member_invitation_text, config("notificationText.member_invitation_create_project"), array(), "member_invitation_create_project", $pass_parameter, $ptid);
            }
            foreach (count($notification_members) > 0 ? $notification_members : array() as $key) {
                if (!isset($ios_device_tokens[$key]) && !isset($android_device_tokens[$key])) {
                    $notification_data = new NotificationDetail();
                    $notification_data->notificationType = "member_invitation_create_project";
                    $notification_data->notificationText = config("notificationText.member_invitation_create_project");
                    $notification_data->sentTime = Carbon::now()->format('Y-m-d h:i:s');
                    $notification_data->sentBy = $creator_data->id;
                    $notification_data->sentTo = $key;
                    $notification_data->parameters = json_encode(array());
                    $notification_data->ptId = $ptid;
                    $notification_data->created_at = Carbon::now()->format('Y-m-d h:i:s');
                    $notification_data->updated_at = Carbon::now()->format('Y-m-d h:i:s');
                    $notification_data->save();
                }
                $pusher = getPusherObject();
                $data = array("user_id" => $key, "text" => $member_invitation_text, "type" => "add_project", "pt_id" => $ptid, "all_ids" => $parent_ids);
                $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($key), 'App\\Events\\ProjectEvent', $data);
            }
            if (count($remaining_user_tokens) > 0) {
                sendSlientNotificationIOS($remaining_user_tokens, $pass_parameter);
            }
            foreach (count($remaining_users) > 0 ? $remaining_users : array() as $rkey) {
                $pusher = getPusherObject();
                $data = array("user_id" => $rkey, "text" => "", "type" => "refresh_list", "pt_id" => (intval($project_detail->parentId) != 0 ? $project_detail->parentId : $ptid), "all_ids" => $parent_ids);
                $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($rkey), 'App\\Events\\ProjectEvent', $data);
            }
        } else {
            $pass_parameter["type"] = "assign";
            $pass_parameter["subtype"] = "task";
            $pass_parameter["task_id"] = $ptid;
            $pass_parameter["project_id"] = intval($project_detail->parentId);
            $parent_project_name = ($project_detail->parentProject ? $project_detail->parentProject->name : "");
            $pass_parameter["project_name"] = $parent_project_name;
            $pass_parameter["project_status"] = ($project_detail->parentProject ? $project_detail->parentProject->status : 1);
            $pass_parameter["all_parent_ids"] = $parent_ids;
            $pass_parameter["all_child_ids"] = getChildIds($project_detail->id, array(), 1);
            $pass_parameter["first_parent_project_id"] = ($project_detail->parentProject ? getFirstParentID($project_detail->parentProject) : 0);

            $create_task_text = config("notificationText.create_task");
            $create_task_text = str_replace(array("{creator}", "{project_name}"), array($creator_data->name, $parent_project_name), $create_task_text);

            $mail_text = $creator_data->name . " assigned a Task to you";
            foreach (count($email_members) > 0 ? $email_members : array() as $key => $value) {
                Notification::send($value, new MailNotification(array('text' => $mail_text, 'subtext' => ($project_name . ($parent_project_name != "" ? " . " . $parent_project_name : "")), 'dueDateText' => ($due_date != "" ? "Due Date : " . $due_date : ""), 'btntext' => 'View this task', 'subject' => ($project_name . " | Added you"))));
            }
            $create_task_text .= " : " . $project_name;
            if (count($ios_device_tokens) > 0) {
                sendNotificationIOS($ios_device_tokens, $create_task_text, config("notificationText.create_task"), array(), "create_task", $pass_parameter, $ptid);
            }
            if (count($android_device_tokens) > 0) {
                sendNotificationAndroid($android_device_tokens, $create_task_text, config("notificationText.create_task"), array(), "create_task", $pass_parameter, $ptid);
            }
            foreach (count($notification_members) > 0 ? $notification_members : array() as $key) {
                if (!isset($ios_device_tokens[$key]) && !isset($android_device_tokens[$key])) {
                    $notification_data = new NotificationDetail();
                    $notification_data->notificationType = "create_task";
                    $notification_data->notificationText = config("notificationText.create_task");
                    $notification_data->sentTime = Carbon::now()->format('Y-m-d h:i:s');
                    $notification_data->sentBy = $creator_data->id;
                    $notification_data->sentTo = $key;
                    $notification_data->parameters = json_encode(array());
                    $notification_data->ptId = $ptid;
                    $notification_data->created_at = Carbon::now()->format('Y-m-d h:i:s');
                    $notification_data->updated_at = Carbon::now()->format('Y-m-d h:i:s');
                    $notification_data->save();
                }
                $pusher = getPusherObject();
                $data = array("user_id" => $key, "text" => $create_task_text, "type" => "add_task", "pt_id" => $project_detail->parentId, "all_ids" => $parent_ids);
                $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($key), 'App\\Events\\ProjectEvent', $data);
            }
            if (count($remaining_user_tokens) > 0) {
                sendSlientNotificationIOS($remaining_user_tokens, $pass_parameter);
            }
            foreach (count($remaining_users) > 0 ? $remaining_users : array() as $rkey) {
                $pusher = getPusherObject();
                $data = array("user_id" => $rkey, "text" => "", "type" => "refresh_list", "pt_id" => $project_detail->parentId, "all_ids" => $parent_ids);
                $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($rkey), 'App\\Events\\ProjectEvent', $data);
            }
        }
    }

    /**
     * Method to assign member to project
     */

    public function inviteMember(Request $request)
    {
        try {
            $userId = Auth::user()->id;
            $element_array = array('projectId' => 'required');
            $validator = Validator::make($request->all(), $element_array, [
                'projectId.required' => 'Please select project'
            ]);
            if ($validator->fails()) {
                return json_encode(array(
                    'response' => "",
                    'error_msg' => $validator->errors()
                ));
            }
            $projectId = $request->input('projectId');
            $project = ProjectTaskDetail:: where('id', $projectId)->first();
            $parent_project_name = "";
            if ($project->parentId != 0) {
                $parentproject = $project->parentId;
                $parentLevel = ProjectTaskDetail::selectRaw('name,parentLevel')->where('id', $parentproject)->first();
                if ($parentLevel) {
                    $parent_project_name = $parentLevel->name;
                }
            }
            if (!$project) {
                return json_encode(array(
                    'response' => "",
                    'error_msg' => "Project not found"
                ));
            }
            $project_members = MemberDetail::where("ptId", $projectId)->get();
            $member_list = json_decode($request->input('members'));
            $email_array = $notification_array = $ios_device_tokens = $android_device_tokens = $remaining_users = $remaining_user_tokens = array();
            if ($member_list != null) {
                foreach (count($member_list) > 0 ? $member_list : array() as $m) {
                    if ($m == Auth::user()->email) {
                        continue;
                    }
                    $existingmember = UserDetail::where('email', $m)->first();
                    if (!$existingmember) {
                        $existing_invitation = InvitationDetail::where("memberEmailID", $m)->where("ptId", $projectId)->where("status", config("constants.invitation_status")["pending"])->count();
                        if ($existing_invitation == 0) {
                            $invitation = new InvitationDetail();
                            $invitation->ptId = $projectId;
                            $project_name = $request->input('projectName');
                            $invitation->memberId = 0;
                            $invitation->memberEmailID = $m;
                            $invitation->sentTime = Carbon::now();
                            $invitation->sentBy = $userId;
                            $invitation->status = config('constants.invitation_status')['pending'];
                            $invitation->save();
                            $due_date = getDueDateTime($project->dueDate, $project->dueDateTime);
                            $mail_text = ($project->type == config('constants.type.project') ? (Auth::user()->name . "  assigned a Project to you") : (Auth::user()->name . " assigned a Task to you"));
                            Notification::send($invitation, new MailNotification(array('text' => $mail_text, 'subtext' => ($project_name . ($parent_project_name != "" ? " . " . $parent_project_name : "")), 'dueDateText' => ($due_date != "" ? "Due Date : " . $due_date : ""), 'btntext' => 'View this project', 'subject' => ($project_name . " | Added you"))));
                        }
                    } else {
                        $member = new MemberDetail();
                        $member->ptId = $projectId;
                        $member->memberId = $existingmember->id;
                        $member->save();

                        $permission = array("add_member" => array("email" => 1, "push_notification" => 1), "task_assigned" => array("email" => 1, "push_notification" => 1));
                        $member_permission = NotificationSettingDetail::where("userId", $member->memberId)->whereIn("notificationType", array("add_member", "task_assigned"))->get();
                        foreach (count($member_permission) > 0 ? $member_permission : array() as $mp) {
                            $permission[$mp->notificationType]["email"] = $mp->email;
                            $permission[$mp->notificationType]["push_notification"] = $mp->pushNotification;
                        }
                        if ($permission["add_member"]["push_notification"]) {
                            if ($existingmember->deviceToken != "") {
                                if ($existingmember->type == intval(config('constants.device_type.ios'))) {
                                    $ios_device_tokens[$existingmember->id] = $existingmember->deviceToken;
                                } else {
                                    $android_device_tokens[$existingmember->id] = $existingmember->deviceToken;
                                }
                            }
                            array_push($notification_array, $existingmember->id);
                        } else {
                            if ($existingmember->deviceToken != "" && $existingmember->type == intval(config('constants.device_type.ios'))) {
                                $remaining_user_tokens[$existingmember->id] = $existingmember->deviceToken;
                            }
                            array_push($remaining_users, $existingmember->id);
                        }
                        if ($permission["add_member"]["email"]) {
                            $email_array[$member->memberId] = $existingmember;
                        }
                    }
                }
                $this->sendNotificationProjectTask($project, $notification_array, $email_array, $ios_device_tokens, $android_device_tokens, $remaining_user_tokens, $remaining_users);
                foreach (count($project_members) > 0 ? $project_members : array() as $pm) {
                    $pusher = getPusherObject();
                    $data = array("user_id" => $pm->memberId, "text" => "", "type" => "member_invited", "pt_id" => $projectId);
                    $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($pm->memberId), 'App\\Events\\ProjectEvent', $data);

                    if ($pm->memberData) {
                        $memberData = $pm->memberData;
                        $pass_parameter["type"] = "update_member";
                        $pass_parameter["subtype"] = "project";
                        $pass_parameter["project_id"] = $project->id;
                        $pass_parameter["project_name"] = $project->name;
                        $pass_parameter["project_status"] = $project->status;
                        $pass_parameter["all_parent_ids"] = getBaseParentId($project, array(), 1);
                        $pass_parameter["all_child_ids"] = getChildIds($project->id, array(), 1);

                        $pass_parameter["members"] = getMembers($project->id);
                        $members = getMemberNames($project->id, $project->createdBy, 1);
                        $pass_parameter['member_names'] = $members["member_names"];
                        $pass_parameter['member_emails'] = $members["member_emails"];
                        $pass_parameter["owner_name"] = $project->creatorData ? $project->creatorData->name : "";
                        if ($memberData->deviceToken != "" && $memberData->type == intval(config('constants.device_type.ios'))) {
                            sendSlientNotificationIOS(array($memberData->id => $memberData->deviceToken), $pass_parameter);
                        }
                    }
                }
            } else {
                return json_encode(array(
                    'response' => "",
                    'error_msg' => "Member(s) not found"
                ));
            }
            return json_encode(array(
                'response' => 'Invitation sent',
                'error_msg' => ''
            ));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Method to upload documents
     */

    private function uploadDocuments($file, $project_id)
    {
        foreach (isset($file) ? $file : array() as $f) {
            $document = new DocumentDetail();
            $document->ptId = $project_id;
            $document->original_name = $f->getClientOriginalName();
            $document->size = number_format((float)($f->getSize() / 1024), 2, '.', '');
            $document->type = $f->getMimeType();
            $document->uploadedBy = Auth::user()->id;
            $document->uploadedTime = Carbon::now();
            $document->save();

            $document->formatted_name = (base64_encode($document->id) . "." . pathinfo($document->original_name, PATHINFO_EXTENSION));
            $document->save();

            $destination_path = public_path("uploads/$project_id/");
            if (!File::exists($destination_path)) {
                File::makeDirectory($destination_path);
            }
            $f->move($destination_path, $document->formatted_name);

            $thumbnail_path = public_path("uploads/$project_id/thumbnail");
            if (!File::exists($thumbnail_path)) {
                File::makeDirectory($thumbnail_path);
            }
            if (strpos($document->type, 'video') !== false) {
                $thumbnail_url = ($document->id . ".jpg");
                generate_video_thumbnail(($destination_path . $document->formatted_name), ($thumbnail_path . "/" . $thumbnail_url));
                $document->videoThumbUrl = $thumbnail_url;
                $document->save();
            }
        }
    }

    /**
     * Method to create tags
     */

    private function tagGeneration($tags)
    {
        $tag_ids = array();
        $tag_list = json_decode($tags, true);
        $tag_list = array_filter($tag_list);
        foreach (count($tag_list) > 0 ? $tag_list : array() as $t) {
            if (trim($t) == "") {
                continue;
            }
            $tag_detail = Tags::where('userId', Auth::user()->id)->where('tagName', $t)->first();
            if (!$tag_detail) {
                $newtag = new Tags();
                $newtag->userId = Auth::user()->id;
                $newtag->tagName = $t;
                $newtag->save();
                array_push($tag_ids, $newtag->id);
            } else {
                array_push($tag_ids, $tag_detail->id);
            }
        }
        return (count($tag_ids) > 0 ? implode(',', $tag_ids) : "");
    }

    /**
     *  Method to update project
     */

    public function updateProject(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "project_id" => 'required',
                "name" => "required",
            ], [
                "project_id.required" => "Please enter project id",
                "name.required" => "Please enter project name",
            ]);
            if ($validator->fails()) {
                return json_encode(array(
                    'response' => '',
                    'error_msg' => $validator->errors()
                ));
            }
            $userId = Auth::user()->id;
            $project_id = $request->input("project_id");
            $project_details = ProjectTaskDetail::where("id", $project_id)->first();
            if (!$project_details) {
                return json_encode(array(
                    'response' => '',
                    'error_msg' => array("not_found" => "Project not found")
                ));
            }
            $old_parent_id = $project_details->parentId;
            $old_data = $project_details;
            $old_order = $project_details->ptOrder;
            $new_status = $request->input("status") ?? 1;
            $repeat = $request->input("repeat") ?? "Never";
            $is_update_status = intval($request->input("isUpdateStatus")) ?? 0;
            $is_repeated = 0;
            $old_status = $project_details->status;
            $project_status = config("constants.project_status");
            if ($new_status == $project_status["review"]) {
                $count = $this->checkStatusOfChilds($project_details->id, 0);
                if ($count != 0) {
                    return json_encode(array(
                        'response' => '',
                        'error_msg' => array("not_found" => "Sub projects/Tasks not completed")
                    ));
                }
                $project_details->send_to_review_by = $userId;
                $project_details->send_to_review_time = Carbon::now();
            } else if ($new_status == $project_status["completed"]) {
                $count = $this->checkStatusOfChilds($project_details->id, 0);
                if ($count != 0) {
                    return json_encode(array(
                        'response' => '',
                        'error_msg' => array("not_found" => "Sub projects/Tasks not completed")
                    ));
                }
                if ($repeat != "Never" && $repeat != "" && ($old_status != $new_status)) {
                    $new_project = repeatChildProjectTask($project_details, $project_details->repeat, 1);
                    if ($new_project) {
                        $is_repeated = 1;
                    }
                }
            } else {
                if (($old_status == $project_status["review"] || $old_status == $project_status["completed"]) && ($new_status == $project_status["active"] || $new_status == $project_status["on_hold"]) && $is_update_status) {
                    changeActiveStatusChilds($project_id);
                }
            }
            $project_details->name = $request->input("name");
            if (!$is_repeated) {
                $project_details->dueDate = "";
                $project_details->dueDateTime = "";
                if ($request->input("date")) {
                    $datetime = Carbon::parse($request->input('date'));
                    $project_details->dueDate = $datetime->format('Y-m-d');
                    $project_details->dueDateTime = $datetime->format('H:i');
                }
                $project_details->status = $new_status;
            }
            $project_details->flag = $request->input("flag") ?? 1;
            $project_details->color = $request->input("color");
            $project_details->note = $request->input("note");
            $project_details->repeat = $repeat;
            $project_details->reminder = $request->input("reminder") ?? "None";
            $project_details->parentId = intval($request->input("parentproject")) ?? 0;

            $project_details->updatedBy = $userId;
            $project_details->tags = "";
            $project_details->parentLevel = 1;
            if ($project_details->parentId != 0) {
                $parentLevel = ProjectTaskDetail::select('parentLevel')->where('id', $project_details->parentId)->first();
                if ($parentLevel) {
                    $project_details->parentLevel = $parentLevel->parentLevel + 1;
                }
            }
            $project_details->save();
            if ($request->input("attachment_ids")) {
                $document_ids = explode(",", $request->input("attachment_ids"));
                $documents = DocumentDetail::whereNotIn("id", $document_ids)->where("ptId", $project_id)->get();
                foreach (count($documents) > 0 ? $documents : array() as $d) {
                    $this->deleteDocument($request, $d);
                }
            } else {
                $documents = DocumentDetail::where("ptId", $project_id)->get();
                foreach (count($documents) > 0 ? $documents : array() as $d) {
                    $this->deleteDocument($request, $d);
                }
            }
            $this->uploadDocuments($request->file('files'), $project_id);

            $this->assignChildProjectLevel($project_id, $project_details->parentLevel);
            $project_details->tags = $this->tagGeneration($request->input('tags'));
            $project_details->save();
            $this->assignMembers($request, $project_details, 1);
            if ($new_status == $project_status["completed"]) {
                sendToReviewProject($project_details);
            }
            if ($new_status == $project_status["completed"]) {
                $this->sendNotificationForCompleteProject($project_details, $is_repeated, 1);
            }
            if ($old_parent_id != $project_details->parentId) {
                refreshOrder($project_details->parentId, 1, $project_details->type, $userId);
                $project_details->ptOrder = 1;
                $project_details->save();

                refreshOldParentOrder($old_parent_id, $old_order, $project_details->type, $userId);
            }
            updateNotification($project_details, $project_id, array("new_parent" => $project_details, "isParent_changed" => ($old_parent_id != $project_details->parentId ? 1 : 0), "old_parent" => $old_data, "new_parent_data" => ($project_details->parentProject ? $project_details->parentProject : "")));
            return json_encode(array(
                'response' => 'Project updated successfully',
                'error_msg' => ''
            ));
        } catch (\Exception $e) {
            return json_encode(array(
                'response' => '',
                'error_msg' => $e->getMessage()
            ));
        }
    }

    /**
     *  Method to update task
     */

    public function updateTask(Request $request)
    {
        try {
            $element_array = array(
                'name' => 'required'
            );
            $validator = Validator::make($request->all(), $element_array, [
                'name.required' => 'Please enter task name'
            ]);
            if ($validator->fails()) {
                return json_encode(array(
                    'response' => '',
                    'error_msg' => $validator->errors()
                ));
            }
            $userId = Auth::user()->id;
            $task_id = $request->input("projectId");
            $task_details = ProjectTaskDetail::where("id", $task_id)->first();
            if (!$task_details) {
                return json_encode(array(
                    'response' => '',
                    'error_msg' => array("not_found" => "Task not found")
                ));
            }
            $old_parent_id = $task_details->parentId;
            $old_order = $task_details->ptOrder;
            $old_data = $task_details;
            $task_details->name = $request->input("name");
            if ($request->input("date")) {
                $datetime = Carbon::parse($request->input('date'));
                $task_details->dueDate = $datetime->format('Y-m-d');
                $task_details->dueDateTime = $datetime->format('H:i');
            }
            $task_details->repeat = $request->input('repeat');
            $task_details->flag = $request->input('flag');
            $task_details->reminder = $request->input('reminder');
            $task_details->parentId = intval($request->input("parentproject")) ?? 0;
            $task_details->updatedBy = $userId;
            $task_details->tags = "";
            $task_details->tags = $this->tagGeneration($request->input('tags'));
            $task_details->save();
            $this->assignMembers($request, $task_details, 1);
            $data = array("new_parent" => $task_details, "old_parent" => $old_data, "isParent_changed" => ($task_details->parentId != $old_parent_id ? 1 : 0), "new_parent_data" => ($task_details->parentProject ? $task_details->parentProject : ""));
            updateNotification($task_details, $task_details->parentId, $data);
            if ($task_details->parentId != $old_parent_id) {
                refreshOrder($task_details->parentId, 1, $task_details->type, $userId);
                $task_details->ptOrder = 1;
                $task_details->parentLevel = 1;
                $task_details->save();

                refreshOldParentOrder($old_parent_id, $old_order, $task_details->type, $userId);
            }
            return json_encode(array(
                'response' => 'Task updated successfully',
                'error_msg' => ''
            ));
        } catch (\Exception $e) {
            return json_encode(array(
                'response' => '',
                'error_msg' => $e->getMessage()
            ));
        }
    }

    /**
     * Check if child projects/tasks completed
     */

    private function checkStatusOfChilds($project_id, $complete_count)
    {
        $get_childs = ProjectTaskDetail::where("parentId", $project_id)->get();
        foreach (count($get_childs) ? $get_childs : array() as $gc) {
            if ($gc->status != config("constants.project_status.completed")) {
                $complete_count += 1;
            }
            $complete_count = $this->checkStatusOfChilds($gc->id, $complete_count);
        }
        return $complete_count;
    }

    /**
     * Recursive method to refresh level to project childs
     */

    private function assignChildProjectLevel($project_id, $level)
    {
        $first_level_child = ProjectTaskDetail::where("parentId", $project_id)->where("type", config("constants.type")["project"])->get();
        foreach (count($first_level_child) > 0 ? $first_level_child : array() as $f) {
            $f->parentLevel = $level + 1;
            $f->save();
            $this->assignChildProjectLevel($f->id, $f->parentLevel);
        }
    }

    /**
     * Method to send notification when assign member or remove member
     */

    private function assignMembers($request, $project_details, $is_update = 0)
    {
        //this request input is also used in add/update project.
        $emails = json_decode($request->input("members"));
        $is_empty_email = 0;
        $removed_member_ids = array();
        $project_members = array();
        $project_id = $project_details->id;
        $project_name = $project_details->name;
        $parent_project_name = $project_details->parentProject ? $project_details->parentProject->name : "";
        $base_parent_ids = getBaseParentId($project_details, array(), 1);
        $all_child_ids = getChildIds($project_id, array(), 1);
        $childs = array_merge(explode(",", $base_parent_ids), explode(",", $all_child_ids));
        $childs = implode(",", $childs);
        $type = array_search($project_details->type, config('constants.type'));
        if ($emails == null) {
            $all_members = MemberDetail::where("memberId", "!=", Auth::user()->id)->where("ptId", $project_id)->get();
            foreach (count($all_members) > 0 ? $all_members : array() as $am) {
                array_push($removed_member_ids, $am->memberId);
            }
            $is_empty_email = 1;
        }
        $ios_device_tokens = $android_device_tokens = $all_member_detail = $web_notification_array = $existing_members = $remaining_users = $remaining_users_token = array();
        $is_added = 0;
        if (!$is_empty_email) {
            $get_project_members = MemberDetail::selectRaw("GROUP_CONCAT(memberId) as members")->where("ptId", $project_id)->first();
            if ($get_project_members) {
                $existing_members = explode(",", $get_project_members->members);
            }
            foreach (count($emails) ? $emails : array() as $email) {
                if ($email == Auth::user()->email) {
                    continue;
                }
                $user_detail = UserDetail::where("email", $email)->where("isVerified", 1)->first();
                if ($user_detail) {
                    $project_members[$user_detail->id] = $user_detail;
                    if (!in_array($user_detail->id, $existing_members)) {
                        $permission = array("add_member" => array("email" => 1, "push_notification" => 1), "task_assigned" => array("email" => 1, "push_notification" => 1));
                        $member_permission = NotificationSettingDetail::where("userId", $user_detail->id)->whereIn("notificationType", array("add_member", "task_assigned"))->get();
                        foreach (count($member_permission) > 0 ? $member_permission : array() as $mp) {
                            $permission[$mp->notificationType]["email"] = $mp->email;
                            $permission[$mp->notificationType]["push_notification"] = $mp->pushNotification;
                        }
                        $project_member = new MemberDetail();
                        $project_member->ptId = $project_id;
                        $project_member->memberId = $user_detail->id;
                        $project_member->save();
                        if ($permission[(($project_details->type == config('constants.type.project')) ? "add_member" : "task_assigned")]["push_notification"]) {
                            if ($user_detail->deviceToken != "") {
                                if ($user_detail->type == intval(config('constants.device_type.ios'))) {
                                    $ios_device_tokens[$user_detail->id] = $user_detail->deviceToken;
                                } else {
                                    $android_device_tokens[$user_detail->id] = $user_detail->deviceToken;
                                }
                            }
                            array_push($web_notification_array, $user_detail->id);
                        } else {
                            if ($user_detail->deviceToken != "" && $user_detail->type == intval(config('constants.device_type.ios'))) {
                                $remaining_users_token[$user_detail->id] = $user_detail->deviceToken;
                            }
                            array_push($remaining_users, $user_detail->id);
                        }
                        if ($permission[(($project_details->type == config('constants.type.project')) ? "add_member" : "task_assigned")]["email"]) {
                            $all_member_detail[$user_detail->id] = $user_detail;
                        }
                        $is_added = $is_added + 1;
                    }
                } else if ($type == "project") {
                    $existing_invitation = InvitationDetail::where("memberEmailID", $email)->where("ptId", $project_id)->where("status", config("constants.invitation_status")["pending"])->count();
                    if ($existing_invitation == 0) {
                        $inviteMember = new InvitationDetail();
                        $inviteMember->ptId = $project_id;
                        $inviteMember->memberId = 0;
                        $inviteMember->memberEmailID = $email;
                        $inviteMember->sentTime = Carbon::now();
                        $inviteMember->sentBy = Auth::user()->id;
                        $inviteMember->status = config("constants.invitation_status")["pending"];
                        $inviteMember->save();
                        $due_date = getDueDateTime($project_details->dueDate, $project_details->dueDateTime);
                        $mail_text = Auth::user()->name . "  assigned a Project to you";
                        Notification::send($inviteMember, new MailNotification(array('text' => $mail_text, 'subtext' => ($project_name . ($parent_project_name != "" ? " . " . $parent_project_name : "")), 'dueDateText' => ($due_date != "" ? "Due Date : " . $due_date : ""), 'btntext' => 'View this project', 'subject' => ($project_name . " | Added you"))));
                        $is_added = $is_added + 1;
                    }
                }
            }
            if (count($project_members) == 0) {
                return true;
            }
            $this->sendNotificationProjectTask($project_details, $web_notification_array, $all_member_detail, $ios_device_tokens, $android_device_tokens, $remaining_users_token, $remaining_users);
            $removed_member_ids = array_diff($existing_members, array_keys($project_members));
        }
        $is_removed = 0;
        $remaining_project_members = $remaining_project_member_ids = array();
        foreach (count($removed_member_ids) > 0 ? $removed_member_ids : array() as $rm) {
            if ($rm == Auth::user()->id) {
                continue;
            }
            $all_notifications = NotificationDetail::where("ptId", $project_id)->where("sentTo", $rm)->get();
            foreach (count($all_notifications) > 0 ? $all_notifications : array() as $an) {
                $an->delete();
            }
            $get_tasks_of_project = getChildIds($project_id, array());
            if (count($get_tasks_of_project) > 0) {
                $existing_notification = NotificationDetail::whereIn("ptId", $get_tasks_of_project)->where("sentTo", $rm)->get();
                foreach (count($existing_notification) > 0 ? $existing_notification : array() as $en) {
                    $en->delete();
                }
                $existing_tasks = MemberDetail::whereIn("ptId", $get_tasks_of_project)->where("memberId", $rm)->get();
                foreach (count($existing_tasks) > 0 ? $existing_tasks : array() as $et) {
                    $et->delete();
                }
            }
            if ($type == "project") {
                $user_detail = UserDetail::where("id", $rm)->where("isVerified", 1)->first();
                if ($user_detail) {
                    $permission = array("member_removed" => array("email" => 1, "push_notification" => 1));
                    $member_permission = NotificationSettingDetail::where("userId", $user_detail->id)->where("notificationType", "member_removed")->first();
                    if ($member_permission) {
                        $permission[$member_permission->notificationType]["email"] = $member_permission->email;
                        $permission[$member_permission->notificationType]["push_notification"] = $member_permission->pushNotification;
                    }
                    $notification_text = config("notificationText.member_removed");
                    $notification_text = str_replace(array("{removed_by}", "{name}"), array(Auth::user()->name, $project_details->name), $notification_text);
                    if ($user_detail->deviceToken != "") {
                        $pass_parameter["type"] = "removed";
                        $pass_parameter["subtype"] = $type;
                        $pass_parameter["project_id"] = $project_id;
                        $pass_parameter["project_name"] = $project_details->name;
                        $pass_parameter["all_parent_ids"] = $base_parent_ids;
                        $pass_parameter["all_child_ids"] = $all_child_ids;

                        if ($user_detail->type == intval(config('constants.device_type.ios'))) {
                            sendNotificationIOS(array($user_detail->id => $user_detail->deviceToken), $notification_text, config("notificationText.member_removed"), array("removed_by" => Auth::user()->id), "member_removed", $pass_parameter, $project_id, 1);
                        } else {
                            sendNotificationAndroid(array($user_detail->id => $user_detail->deviceToken), $notification_text, config("notificationText.member_removed"), array("removed_by" => Auth::user()->id), "member_removed", $pass_parameter, $project_id, 1);
                        }
                    } else {
                        $notification_data = new NotificationDetail();
                        $notification_data->notificationType = "member_removed";
                        $notification_data->notificationText = config("notificationText.member_removed");
                        $notification_data->sentTime = Carbon::now()->format('Y-m-d h:i:s');
                        $notification_data->sentBy = Auth::user()->id;
                        $notification_data->sentTo = $user_detail->id;
                        $notification_data->parameters = json_encode(array("removed_by" => Auth::user()->id));
                        $notification_data->ptId = $project_id;
                        $notification_data->created_at = Carbon::now()->format('Y-m-d h:i:s');
                        $notification_data->updated_at = Carbon::now()->format('Y-m-d h:i:s');
                        $notification_data->save();
                    }
                    $pusher = getPusherObject();
                    $data = array("user_id" => $user_detail->id, "text" => "", "type" => "member_removed", "pt_id" => $project_id, "slient_msg" => $notification_text);
                    $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($user_detail->id), 'App\\Events\\ProjectEvent', $data);
                    if ($permission["member_removed"]["email"]) {
                        Notification::send($user_detail, new MailNotification(array('text' => $notification_text, 'subtext' => '', 'btntext' => 'View application', 'subject' => ($project_details->name . " | Removed from project"))));
                    }
                    $remove_notification_text = config("notificationText.member_removed_by");
                    $remove_notification_text = str_replace(array("{removed_by}", "{user}", "{name}"), array(Auth::user()->name, $user_detail->name, $project_details->name), $remove_notification_text);
                    foreach (count($project_members) > 0 ? $project_members : array() as $key => $pm) {
                        $remove_permission = array("member_removed" => array("email" => 1, "push_notification" => 1));
                        $pm_permission = NotificationSettingDetail::where("userId", $key)->where("notificationType", "member_removed")->first();
                        if ($pm_permission) {
                            $remove_permission[$pm_permission->notificationType]["email"] = $pm_permission->email;
                            $remove_permission[$pm_permission->notificationType]["push_notification"] = $pm_permission->pushNotification;
                        }
                        if ($remove_permission["member_removed"]["push_notification"]) {
                            if ($pm->deviceToken != "") {
                                $pass_parameter["type"] = "removed_by";
                                $pass_parameter["subtype"] = $type;
                                $pass_parameter["project_id"] = $project_id;
                                $pass_parameter["project_name"] = $project_details->name;
                                $pass_parameter["removed_member_name"] = $user_detail->name;
                                $pass_parameter["project_status"] = $project_details->status;
                                $pass_parameter["all_parent_ids"] = $base_parent_ids;
                                $pass_parameter["all_child_ids"] = $all_child_ids;
                                $members = getMemberNames($project_details->id, $project_details->createdBy, 0);
                                $pass_parameter['member_names'] = $members["member_names"];
                                $pass_parameter['member_emails'] = $members["member_emails"];

                                if ($pm->type == intval(config('constants.device_type.ios'))) {
                                    sendNotificationIOS(array($pm->id => $pm->deviceToken), $remove_notification_text, config("notificationText.member_removed_by"), array("removed_by" => Auth::user()->id, "removed_member" => $user_detail->id), "member_removed_by", $pass_parameter, $project_id);
                                } else {
                                    sendNotificationAndroid(array($pm->id => $pm->deviceToken), $remove_notification_text, config("notificationText.member_removed_by"), array("removed_by" => Auth::user()->id, "removed_member" => $user_detail->id), "member_removed_by", $pass_parameter, $project_id);
                                }
                            } else {
                                $notification_data = new NotificationDetail();
                                $notification_data->notificationType = "member_removed_by";
                                $notification_data->notificationText = config("notificationText.member_removed_by");
                                $notification_data->sentTime = Carbon::now()->format('Y-m-d h:i:s');
                                $notification_data->sentBy = Auth::user()->id;
                                $notification_data->sentTo = $pm->id;
                                $notification_data->parameters = json_encode(array("removed_by" => Auth::user()->id, "removed_member" => $user_detail->id));
                                $notification_data->ptId = $project_id;
                                $notification_data->created_at = Carbon::now()->format('Y-m-d h:i:s');
                                $notification_data->updated_at = Carbon::now()->format('Y-m-d h:i:s');
                                $notification_data->save();
                            }
                            $pusher = getPusherObject();
                            $r_project_id = ($project_details->type == config("constants.type.project") ? $project_details->id : $project_details->parentId);
                            $data = array("user_id" => $pm->id, "text" => $remove_notification_text, "type" => "member_removed_by", "pt_id" => $r_project_id);
                            $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($pm->id), 'App\\Events\\ProjectEvent', $data);
                        } else {
                            if (!in_array($pm->id, $remaining_project_member_ids)) {
                                array_push($remaining_project_member_ids, $pm->id);
                            }
                            if ($pm->deviceToken != "" && $pm->type == intval(config('constants.device_type.ios')) && !isset($remaining_project_members[$pm->id])) {
                                $remaining_project_members[$pm->id] = $pm->deviceToken;
                            }
                        }
                        if ($remove_permission["member_removed"]["email"]) {
                            Notification::send($pm, new MailNotification(array('text' => $remove_notification_text, 'subtext' => '', 'btntext' => 'View this project', 'subject' => ($user_detail->name . " | Removed from project"))));
                        }
                    }
                }
            } else {
                $user_detail = UserDetail::where("id", $rm)->where("isVerified", 1)->first();
                if ($user_detail) {
                    if ($user_detail->deviceToken != "") {
                        $pass_parameter["type"] = "removed_task";
                        $pass_parameter["subtype"] = $type;
                        $pass_parameter["project_id"] = $project_id;
                        $pass_parameter["project_name"] = $project_details->name;
                        $pass_parameter["parent_id"] = (string)$project_details->parentId;
                        $pass_parameter["all_parent_ids"] = $base_parent_ids;
                        $pass_parameter["all_child_ids"] = $all_child_ids;
                        $first_parent_id = ($project_details->parentProject ? getFirstParentID($project_details->parentProject) : 0);
                        $pass_parameter["first_parent_project_id"] = $first_parent_id;
                        $pass_parameter["no_of_tasks"] = 0;

                        if ($user_detail->type == intval(config('constants.device_type.ios'))) {
                            sendNotificationIOS(array($user_detail->id => $user_detail->deviceToken), '', '', array(), '', $pass_parameter, $project_id, 1);
                        } else {
                            sendNotificationAndroid(array($user_detail->id => $user_detail->deviceToken), '', '', array(), '', $pass_parameter, $project_id, 1);
                        }
                    }
                    $pusher = getPusherObject();
                    $data = array("user_id" => $user_detail->id, "text" => "", "type" => "removed_task", "pt_id" => $project_details->parentId, "all_ids" => $childs);
                    $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($user_detail->id), 'App\\Events\\ProjectEvent', $data);
                    foreach (count($project_members) > 0 ? $project_members : array() as $key => $pm) {
                        array_push($remaining_project_member_ids, $pm->id);
                        if ($pm->deviceToken != "" && $pm->type == intval(config('constants.device_type.ios'))) {
                            $remaining_project_members[$pm->id] = $pm->deviceToken;
                        }
                    }
                }
            }
            $is_removed = $is_removed + 1;
            $member_data = MemberDetail::where("ptId", $project_id)->where("memberId", $rm)->first();
            if ($member_data) {
                $member_data->delete();
            }
        }
        $members = getMemberNames($project_details->id, $project_details->createdBy, ($type == "project" ? 0 : 1));
        if ($is_added > 0 && $is_removed > 0) {
            $pass_parameter["type"] = "update_member";
            $pass_parameter["subtype"] = $type;
            if ($type == "project") {
                $pass_parameter["project_id"] = $project_id;
                $pass_parameter["project_name"] = $project_details->name;
                $pass_parameter["project_status"] = $project_details->status;
            } else {
                $pass_parameter["task_id"] = $project_id;
                $pass_parameter["project_id"] = intval($project_details->parentId);
                $pass_parameter["project_name"] = $parent_project_name;
                $pass_parameter["project_status"] = ($project_details->parentProject ? $project_details->parentProject->status : 1);
                $pass_parameter["first_parent_project_id"] = ($project_details->parentProject ? getFirstParentID($project_details->parentProject) : 0);
            }
            $pass_parameter["members"] = getMembers($project_id);
            $pass_parameter['member_names'] = $members["member_names"];
            $pass_parameter['member_emails'] = $members["member_emails"];
            $pass_parameter["owner_name"] = $project_details->creatorData ? $project_details->creatorData->name : "";
            $pass_parameter["all_parent_ids"] = $base_parent_ids;
            $pass_parameter["all_child_ids"] = $all_child_ids;
            $r_project_id = ($type == "project" ? $project_details->id : $project_details->parentId);
            foreach (count($project_members) > 0 ? $project_members : array() as $key => $pm) {
                if ($pm->deviceToken != "" && $pm->type == intval(config('constants.device_type.ios'))) {
                    sendSlientNotificationIOS(array($pm->id => $pm->deviceToken), $pass_parameter);
                }
                if (!$is_update) {
                    $pusher = getPusherObject();
                    $data = array("user_id" => $pm->id, "text" => "", "type" => "updated_member", "pt_id" => $r_project_id, "all_ids" => $childs);
                    $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($pm->id), 'App\\Events\\ProjectEvent', $data);
                }
            }
        } else {
            if ($is_removed > 0) {
                $pass_parameter = array();
                if (count($remaining_project_members) > 0) {
                    $pass_parameter["type"] = "update_member";
                    $pass_parameter["subtype"] = $type;
                    if ($type == "project") {
                        $pass_parameter["project_id"] = $project_id;
                        $pass_parameter["project_name"] = $project_details->name;
                        $pass_parameter["project_status"] = $project_details->status;
                    } else {
                        $pass_parameter["task_id"] = $project_id;
                        $pass_parameter["project_id"] = intval($project_details->parentId);
                        $pass_parameter["project_name"] = $parent_project_name;
                        $pass_parameter["project_status"] = ($project_details->parentProject ? $project_details->parentProject->status : 1);
                        $pass_parameter["first_parent_project_id"] = ($project_details->parentProject ? getFirstParentID($project_details->parentProject) : 0);
                    }
                    $pass_parameter["members"] = getMembers($project_id);
                    $pass_parameter['member_names'] = $members["member_names"];
                    $pass_parameter['member_emails'] = $members["member_emails"];
                    $pass_parameter["owner_name"] = $project_details->creatorData ? $project_details->creatorData->name : "";
                    $pass_parameter["all_parent_ids"] = $base_parent_ids;
                    $pass_parameter["all_child_ids"] = $all_child_ids;
                    sendSlientNotificationIOS($remaining_project_members, $pass_parameter);
                }
                if (!$is_update) {
                    $r_project_id = ($type == "project" ? $project_details->id : $project_details->parentId);
                    foreach (count($remaining_project_member_ids) > 0 ? $remaining_project_member_ids : array() as $id) {
                        $pusher = getPusherObject();
                        $data = array("user_id" => $id, "text" => "", "type" => "updated_member", "pt_id" => $r_project_id, "all_ids" => $childs);
                        $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($id), 'App\\Events\\ProjectEvent', $data);
                    }
                }
            } else if ($is_added > 0) {
                $pass_parameter["type"] = "update_member";
                $pass_parameter["subtype"] = $type;
                if ($type == "project") {
                    $pass_parameter["project_id"] = $project_id;
                    $pass_parameter["project_name"] = $project_details->name;
                    $pass_parameter["project_status"] = $project_details->status;
                } else {
                    $pass_parameter["task_id"] = $project_id;
                    $pass_parameter["project_id"] = intval($project_details->parentId);
                    $pass_parameter["project_name"] = $parent_project_name;
                    $pass_parameter["project_status"] = ($project_details->parentProject ? $project_details->parentProject->status : 1);
                    $pass_parameter["first_parent_project_id"] = ($project_details->parentProject ? getFirstParentID($project_details->parentProject) : 0);
                }
                $pass_parameter["members"] = getMembers($project_id);
                $pass_parameter['member_names'] = $members["member_names"];
                $pass_parameter['member_emails'] = $members["member_emails"];
                $pass_parameter["owner_name"] = $project_details->creatorData ? $project_details->creatorData->name : "";
                $pass_parameter["all_parent_ids"] = $base_parent_ids;
                $pass_parameter["all_child_ids"] = $all_child_ids;
                foreach (count($project_members) > 0 ? $project_members : array() as $key => $pm) {
                    if ($pm->deviceToken != "" && $pm->type == intval(config('constants.device_type.ios'))) {
                        sendSlientNotificationIOS(array($pm->id => $pm->deviceToken), $pass_parameter);
                    }
                    if (!$is_update) {
                        $pusher = getPusherObject();
                        $r_project_id = ($type == "project" ? $project_details->id : $project_details->parentId);
                        $data = array("user_id" => $pm->id, "text" => "", "type" => "updated_member", "pt_id" => $r_project_id, "all_ids" => $childs);
                        $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($pm->id), 'App\\Events\\ProjectEvent', $data);
                    }
                }
            }
        }
        if ($is_added > 0 || $is_removed > 0) {
            if ($project_details->creatorData) {
                $pass_parameter["type"] = "update_member";
                $pass_parameter["subtype"] = $type;
                if ($type == "project") {
                    $pass_parameter["project_id"] = $project_id;
                    $pass_parameter["project_name"] = $project_details->name;
                    $pass_parameter["project_status"] = $project_details->status;
                } else {
                    $pass_parameter["task_id"] = $project_id;
                    $pass_parameter["project_id"] = intval($project_details->parentId);
                    $pass_parameter["project_name"] = $parent_project_name;
                    $pass_parameter["project_status"] = ($project_details->parentProject ? $project_details->parentProject->status : 1);
                    $pass_parameter["first_parent_project_id"] = ($project_details->parentProject ? getFirstParentID($project_details->parentProject) : 0);
                }
                $pass_parameter["members"] = getMembers($project_id);
                $pass_parameter['member_names'] = $members["member_names"];
                $pass_parameter['member_emails'] = $members["member_emails"];
                $pass_parameter["owner_name"] = $project_details->creatorData ? $project_details->creatorData->name : "";
                $pass_parameter["all_parent_ids"] = $base_parent_ids;
                $pass_parameter["all_child_ids"] = $all_child_ids;
                $creator_data = $project_details->creatorData;
                if ($creator_data->deviceToken != "" && $creator_data->type == intval(config('constants.device_type.ios'))) {
                    sendSlientNotificationIOS(array($creator_data->id => $creator_data->deviceToken), $pass_parameter);
                }
                if (!$is_update) {
                    $r_project_id = ($type == "project" ? $project_details->id : $project_details->parentId);
                    $pusher = getPusherObject();
                    $data = array("user_id" => $creator_data->id, "text" => "", "type" => "updated_member", "pt_id" => $r_project_id, "all_ids" => $childs);
                    $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($creator_data->id), 'App\\Events\\ProjectEvent', $data);
                }
            }
        }
    }


    /**
     *  Method to add comment in comment Modal
     */

    public function addComment(Request $request)
    {
        try {
            $element_array = array(
                'comment' => 'required_without:file',
                'file' => 'required_without:comment'
            );
            $validator = Validator::make($request->all(), $element_array, [
                'comment.required_without' => 'Please enter comment ',
                'file.required_without' => 'Please select file '
            ]);
            if ($validator->fails()) {
                return json_encode(array(
                    'response' => '',
                    'error_msg' => $validator->errors()->first()
                ));
            }
            $ptid = $request->input('projectId');
            $project_detail = ProjectTaskDetail::where("id", $ptid)->first();
            if (!$project_detail) {
                return json_encode(array(
                    'response' => '',
                    'error_msg' => "Project/task not found"
                ));
            }
            $userId = Auth::user()->id;
            $comment = new CommentDetail();
            $comment->pt_id = $ptid;
            $comment->comment = $request->input('comment');
            $parentId = $request->input("commentParentId") ?? 0;
            $comment->parentId = $parentId;
            $parentLevel = CommentDetail::select("parentLevel")->where("id", $parentId)->first();
            $comment->parentLevel = ($parentLevel == null) ? 1 : (($parentLevel->parentLevel < 2) ? ($parentLevel->parentLevel + 1) : 1);
            $comment->commentedBy = $userId;
            $comment->commentedTime = Carbon::now()->format("Y-m-d H:i:s");
            $comment->save();

            $file = null;
            if (count($_FILES) > 0) {
                $file = $_FILES;
            }
            if ($file != null) {
                foreach ($file as $f) {
                    $file_name = (base64_encode($comment->id) . "." . pathinfo($f['name'], PATHINFO_EXTENSION));
                    $comment->documentName = $file_name;
                    $comment->originalName = $f['name'];
                    $comment->documentSize = number_format((float)($f['size'] / 1024), 2, '.', '');
                    $comment->documentType = $f['type'];
                    $destination_path = public_path("uploads/$ptid/comment");
                    if (!File::exists($destination_path)) {
                        File::makeDirectory($destination_path);
                    }
                    $destination_path .= '/' . $file_name;
                    move_uploaded_file($f['tmp_name'], $destination_path);

                    $thumbnail_path = public_path("uploads/$ptid/comment/thumbnail");
                    if (!File::exists($thumbnail_path)) {
                        File::makeDirectory($thumbnail_path);
                    }
                    if (strpos($comment->documentType, 'video') !== false) {
                        $thumbnail_url = $comment->id . '.jpg';
                        generate_video_thumbnail($destination_path, $thumbnail_path . '/' . $thumbnail_url);
                        $comment->documentThumbUrl = $thumbnail_url;
                    }
                    $comment->save();
                }
            }

            $member_details = MemberDetail::where("ptId", $ptid)->get();
            $notification_array = $email_array = $ios_device_tokens = $android_device_tokens = $remaining_users = $remaining_user_token = array();
            foreach (count($member_details) > 0 ? $member_details : array() as $m) {
                if (isset($m->memberData)) {
                    $memberData = $m->memberData;
                    if ($memberData->id == $userId) {
                        if ($memberData->deviceToken != "" && $memberData->type == intval(config('constants.device_type.ios'))) {
                            $remaining_user_token[$memberData->id] = $memberData->deviceToken;
                        }
                        array_push($remaining_users, $memberData->id);
                        continue;
                    }
                    $permission = array("email" => 1, "push_notification" => 1);
                    $member_permission = NotificationSettingDetail::where("userId", $memberData->id)->where("notificationType", "add_comment")->first();
                    if ($member_permission) {
                        $permission["email"] = $member_permission->email;
                        $permission["push_notification"] = $member_permission->pushNotification;
                    }
                    if ($permission["push_notification"]) {
                        if ($memberData->deviceToken != "") {
                            if ($memberData->type == intval(config('constants.device_type.ios'))) {
                                $ios_device_tokens[$memberData->id] = $memberData->deviceToken;
                            } else {
                                $android_device_tokens[$memberData->id] = $memberData->deviceToken;
                            }
                        }
                        array_push($notification_array, $memberData->id);
                    } else {
                        if ($memberData->deviceToken != "" && $memberData->type == intval(config('constants.device_type.ios'))) {
                            $remaining_user_token[$memberData->id] = $memberData->deviceToken;
                        }
                        array_push($remaining_users, $memberData->id);
                    }
                    if ($permission["email"]) {
                        $email_array[$memberData->id] = $memberData;
                    }
                }
            }
            $project_name = ($project_detail ? $project_detail->name : "");
            $pass_parameter["type"] = "comment";
            $pass_parameter["subtype"] = array_search($project_detail->type, config('constants.type'));
            $pass_parameter["project_id"] = $project_detail->id;
            $pass_parameter["project_name"] = $project_name;
            $pass_parameter["CmtCount"] = CommentDetail::where('pt_id', $ptid)->count();

            $add_comment_text = config("notificationText.add_comment");
            $add_comment_text = str_replace(array("{commented_by}", "{name}"), array(Auth::user()->name, $project_name), $add_comment_text);

            foreach (count($email_array) > 0 ? $email_array : array() as $m) {
                Notification::send($m, new MailNotification(array('text' => $add_comment_text, 'subtext' => $comment->comment, 'btntext' => 'Reply', 'subject' => ("Re : " . $project_name))));
            }
            $add_comment_text .= " : " . $comment->comment;
            if (count($ios_device_tokens) > 0) {
                sendNotificationIOS($ios_device_tokens, $add_comment_text, config("notificationText.add_comment"), array("commented_by" => $userId, "comment_id" => $comment->id), "add_comment", $pass_parameter, $ptid);
            }
            if (count($android_device_tokens) > 0) {
                sendNotificationAndroid($android_device_tokens, $add_comment_text, config("notificationText.add_comment"), array("commented_by" => $userId, "comment_id" => $comment->id), "add_comment", $pass_parameter, $ptid);
            }
            $project_id = ($project_detail->type == config("constants.type.project") ? $project_detail->id : $project_detail->parentId);
            $all_parent_ids = getBaseParentId($project_detail, array());
            $all_child_ids = getChildIds($project_detail->id, array());
            if (count($all_child_ids) > 0) {
                $all_parent_ids = array_merge($all_parent_ids, $all_child_ids);
            }
            $childs = implode(",", $all_parent_ids);
            foreach (count($notification_array) > 0 ? $notification_array : array() as $n) {
                if (!isset($ios_device_tokens[$n]) && !isset($android_device_tokens[$n])) {
                    $notification_data = new NotificationDetail();
                    $notification_data->notificationType = "add_comment";
                    $notification_data->notificationText = config("notificationText.add_comment");
                    $notification_data->sentTime = Carbon::now()->format('Y-m-d h:i:s');
                    $notification_data->sentBy = Auth::user()->id;
                    $notification_data->sentTo = $n;
                    $notification_data->parameters = json_encode(array("commented_by" => $userId, "comment_id" => $comment->id));
                    $notification_data->ptId = $ptid;
                    $notification_data->created_at = Carbon::now()->format('Y-m-d h:i:s');
                    $notification_data->updated_at = Carbon::now()->format('Y-m-d h:i:s');
                    $notification_data->save();
                }

                $pusher = getPusherObject();
                $project_name = $project_detail->name;
                $data = array("user_id" => $n, "text" => $add_comment_text, "type" => "add_comment", "pt_id" => $project_id, "display_data" => array("project_id" => $project_detail->id, "project_name" => $project_name), "all_ids" => $childs);
                $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($n), 'App\\Events\\ProjectEvent', $data);
            }
            if (count($remaining_user_token) > 0) {
                sendSlientNotificationIOS($remaining_user_token, $pass_parameter);
            }
            foreach ($remaining_users as $ru) {
                $pusher = getPusherObject();
                $data = array("user_id" => $ru, "text" => "", "type" => "comment_list", "pt_id" => intval($project_detail->parentId), "comment_pt_id" => $project_detail->id, "all_ids" => $childs);
                $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($ru), 'App\\Events\\ProjectEvent', $data);
            }
            return json_encode(array(
                'response' => 'Comment Added',
                'error_msg' => ''
            ));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     *  Method to update comment
     */

    public function updateComment(Request $request)
    {
        try {
            $element_array = array(
                'commentId' => 'required',
                'comment' => 'required'
            );
            $validator = Validator::make($request->all(), $element_array, [
                'commentId.required' => 'Please select comment',
                'comment.required' => 'Please enter comment'
            ]);
            if ($validator->fails()) {
                return json_encode(array(
                    'response' => '',
                    'error_msg' => $validator->errors()->first()
                ));
            }
            $commentId = $request->input('commentId');
            $comment = CommentDetail::where('id', $commentId)->first();
            if (!$comment) {
                return json_encode(array(
                    'response' => '',
                    'error_msg' => "Comment data not found"
                ));
            }
            $comment->comment = $request->input('comment');
            $comment->commentedBy = Auth::user()->id;
            $comment->commentedTime = Carbon::now();
            $comment->save();
            if (isset($comment->projectDetail)) {
                sendCommentNotification($comment->projectDetail, "edit_comment");
            }
            return json_encode(array(
                'response' => 'Comment Updated',
                'error_msg' => ''
            ));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     *  Method to delete comment
     */

    public function deleteComment(Request $request, $commentId = 0)
    {
        try {
            $is_private_call = 1;
            if ($commentId == 0) {
                $validator = Validator::make(
                    $request->all(), ['commentId' => 'required'],
                    ['commentId.required' => 'Please select comment']
                );
                if ($validator->fails()) {
                    return json_encode(array(
                        'response' => '',
                        'error_msg' => $validator->errors()->first()
                    ));
                }
                $commentId = $request->input('commentId');
                $is_private_call = 0;
            }

            $comment = CommentDetail::where('id', $commentId)->first();
            if (!$comment) {
                return !$is_private_call ? json_encode(array('response' => '', 'error_msg' => 'Comment not found')) : true;
            }
            $child_comments = CommentDetail::where('pt_id', $comment->pt_id)->where('parentId', $commentId)->get();
            foreach (count($child_comments) > 0 ? $child_comments : array() as $c) {
                if ($c->documentName != '') {
                    unlink(public_path('uploads/' . $c->pt_id . '/comment/' . $c->documentName));
                }
                if ($c->documentThumbUrl != '') {
                    unlink(public_path('uploads/' . $c->pt_id . '/comment/thumbnail/' . $c->documentThumbUrl));
                }
                $child_notifications = NotificationDetail::whereRaw('JSON_EXTRACT(parameters, "$.comment_id") = ' . $c->id)->get();
                foreach (count($child_notifications) > 0 ? $child_notifications : array() as $cn) {
                    $cn->delete();
                }
                $c->delete();
            }
            if ($comment->documentName != '') {
                unlink(public_path('uploads/' . $comment->pt_id . '/comment/' . $comment->documentName));
            }
            if ($comment->documentThumbUrl != '') {
                unlink(public_path('uploads/' . $comment->pt_id . '/comment/thumbnail/' . $comment->documentThumbUrl));
            }
            $notifications = NotificationDetail::whereRaw('JSON_EXTRACT(parameters, "$.comment_id") = ' . $commentId)->get();
            foreach (count($notifications) > 0 ? $notifications : array() as $n) {
                $n->delete();
            }
            $comment->delete();
            if (!$is_private_call && isset($comment->projectDetail)) {
                sendCommentNotification($comment->projectDetail, "delete_comment");
            }
            return !$is_private_call
                ? json_encode(array(
                    'response' => 'Comment Deleted',
                    'error_msg' => ''
                ))
                : true;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     *  Method to remove member from project
     *
     */

    public function removeMember(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), ["projectId" => 'required'], [
                "projectId.required" => "Please enter project id",
            ]);
            if ($validator->fails()) {
                return json_encode(array(
                    'error_msg' => $validator->errors()->first(),
                    'response' => ''
                ));
            }
            $project_id = $request->input("projectId");
            $project_details = ProjectTaskDetail::where("id", $project_id)->first();
            if (!$project_details) {
                return json_encode(array(
                    'error_msg' => "Project not found",
                    'response' => ''
                ));
            }
            $this->assignMembers($request, $project_details);
            return json_encode(array(
                'error_msg' => "",
                'response' => "Member removed successfully"
            ));
        } catch (\Exception $e) {
            return json_encode(array(
                'error_msg' => $e->getMessage(),
                'response' => ""
            ));
        }

    }

    /**
     *  Method to add document
     */

    public function updateAttachment(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "projectId" => "required",
                "files" => "required"
            ], [
                "projectId.required" => "Please select project",
                "files.required" => "Please upload project document"
            ]);
            if ($validator->fails()) {
                return json_encode(array(
                    'response' => '',
                    'error_msg' => $validator->errors()->first()
                ));
            }
            $projectId = $request->input('projectId');
            $project_detail = ProjectTaskDetail::where("id", $projectId)->first();
            if (!$project_detail) {
                return json_encode(array(
                    'response' => '',
                    'error_msg' => "Project/task not found"
                ));
                return $this->sendResultJSON("2", "Project/task not found");
            }

            $file = $request->file('files');
            $this->uploadDocuments($file, $projectId);
            sendAttachmentNotification($project_detail, "attachment");
            return json_encode(array(
                'response' => 'Attachment Updated',
                'error_msg' => ''
            ));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     *  Method to delete document
     */

    public function deleteDocument(Request $request, $document_id = 0)
    {
        try {
            $is_private_call = 1;
            if ($document_id == 0) {
                $validator = Validator::make(
                    $request->all(),
                    ['documentId' => 'required'],
                    ['documentId.required' => 'Please select document id']
                );
                if ($validator->fails()) {
                    return json_encode(array(
                        'response' => '',
                        'error_msg' => $validator->errors()->first()
                    ));
                }
                $is_private_call = 0;
                $document_id = $request->input('documentId');
            }
            $document_details = DocumentDetail::where('id', $document_id)->first();
            if (!$document_details) {
                return !$is_private_call ? json_encode(array('response' => '', 'error_msg' => 'Document not found')) : true;
            }
            unlink(public_path('uploads/' . $document_details->ptId . '/' . $document_details->formatted_name));
            if ($document_details->videoThumbUrl != '') {
                unlink(public_path('uploads/' . $document_details->ptId . '/thumbnail/' . $document_details->videoThumbUrl));
            }
            $document_details->delete();
            if (!$is_private_call && isset($document_details->projectDetail)) {
                sendAttachmentNotification($document_details->projectDetail, "delete_attachment");
            }
            return !$is_private_call ? json_encode(array('response' => 'Document deleted successfully', 'error_msg' => '')) : true;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     *  Method to delete multiple document
     */

    public function deleteMultipleDocument(Request $request)
    {
        try {
            $validator = Validator::make(
                $request->all(),
                ['documentIds' => 'required'],
                ['documentIds.required' => 'Please select document id(s)']
            );
            if ($validator->fails()) {
                return json_encode(array(
                    'response' => '',
                    'error_msg' => $validator->errors()->first()
                ));
            }
            $document_id = explode(",", $request->input('documentIds'));
            foreach (count($document_id) > 0 ? $document_id : array() as $d) {
                $document_details = DocumentDetail::where('id', $d)->first();
                if (!$document_details) {
                    continue;
                }
                unlink(public_path('uploads/' . $document_details->ptId . '/' . $document_details->formatted_name));
                if ($document_details->videoThumbUrl != '') {
                    unlink(public_path('uploads/' . $document_details->ptId . '/thumbnail/' . $document_details->videoThumbUrl));
                }
                $document_details->delete();
            }
            if ($request->input("projectId")) {
                $project_data = ProjectTaskDetail::where("id", $request->input("projectId"))->first();
                if ($project_data) {
                    sendAttachmentNotification($project_data, "delete_attachment");
                }
            }
            return json_encode(array(
                'response' => 'Document(s) deleted successfully',
                'error_msg' => ''
            ));
        } catch (\Exception $e) {
            return json_encode(array(
                'response' => '',
                'error_msg' => $e->getMessage()
            ));
        }
    }

    /**
     *  Method to add tag
     */

    public function addTag(Request $request)
    {
        try {
            $user_id = Auth::user()->id;
            $validator = Validator::make($request->all(), [
                "name" => "required|unique:tags,tagName,NULL,id,userId," . $user_id . ",deleted_at,NULL"
            ], [
                "name.required" => "Please enter tag name",
                "name.unique" => "Tag name exist"
            ]);
            if ($validator->fails()) {
                return json_encode(array(
                    'response' => '',
                    'error_msg' => $validator->errors()->first()
                ));
            }
            $tag = new Tags();
            $tag->userId = $user_id;
            $tag->tagName = $request->input('name');
            $tag->save();
            return json_encode(array(
                'response' => 'Tag added successfully',
                'error_msg' => ''
            ));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     *  Method to update tag
     */

    public function editTag(Request $request)
    {
        try {
            $user_id = Auth::user()->id;
            $tagId = $request->input('id');
            $tag = Tags::where('id', $tagId)->where('userId', $user_id)->first();
            if (!$tag) {
                return json_encode(array(
                    'response' => '',
                    'error_msg' => "Tag not found"
                ));
            }
            $validator = Validator::make($request->all(), [
                "name" => "required|unique:tags,tagName," . $tagId . ",id,userId," . $user_id . ",deleted_at,NULL",
            ], [
                "name.required" => "Please enter tag name",
                "name.unique" => "Tag name exist"
            ]);
            if ($validator->fails()) {
                return json_encode(array(
                    'response' => '',
                    'error_msg' => $validator->errors()->first()
                ));
            }

            $tag->tagName = $request->input('name');
            $tag->save();
            return json_encode(array(
                'response' => 'Tag updated successfully',
                'error_msg' => ''
            ));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     *  Method to delete tag
     */

    public function deleteTag(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "tag_id" => "required"
            ], [
                "tag_id.required" => "Please select tag"
            ]);
            if ($validator->fails()) {
                return json_encode(array(
                    'response' => '',
                    'error_msg' => $validator->errors()->first()
                ));
            }
            $tag_id = $request->input('tag_id');
            $tag = Tags::where('userId', Auth::user()->id)->where('id', $tag_id)->first();
            if (!$tag) {
                return json_encode(array(
                    'response' => '',
                    'error_msg' => "Tag not found"
                ));
            }
            $get_projects = ProjectTaskDetail::whereRaw("FIND_IN_SET(" . $tag->id . ",tags) > 0")->get();
            foreach (count($get_projects) > 0 ? $get_projects : array() as $p) {
                if ($p->tags != null) {
                    $tags = explode(",", $p->tags);
                    unset($tags[array_search($tag->id, $tags)]);
                    $p->tags = implode(",", $tags);
                    $p->save();
                }
            }
            $tag->delete();
            return json_encode(array(
                'response' => 'Tag deleted successfully',
                'error_msg' => ''
            ));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     *  Method to delete project/task
     */

    public function deleteProjectTask(Request $request)
    {
        try {
            $validator = Validator::make(
                $request->all(),
                ['projectTaskId' => 'required'],
                ['projectTaskId.required' => 'Please select project/task']
            );
            if ($validator->fails()) {
                return json_encode(array(
                    'error_msg' => $validator->errors()->first(),
                    'response' => ''
                ));
            }
            $id = $request->input('projectTaskId');
            $details = ProjectTaskDetail::where('id', $id)->first();
            if (!$details) {
                return json_encode(array(
                    'error_msg' => 'Project/Task not found',
                    'response' => ''
                ));
            }
            $type = array_search($details->type, config('constants.type'));
            $this->sendNotificationDeleteProjectTask($details);
            $this->recursiveDelete($request, $details);
            return json_encode(array(
                'error_msg' => '',
                'response' => ucfirst($type) . " deleted successfully"
            ));
        } catch (\Exception $e) {
            return json_encode(array(
                'error_msg' => $e->getMessage(),
                'response' => ""
            ));
        }
    }

    private function sendNotificationDeleteProjectTask($details)
    {
        $id = $details->id;
        $member_details = MemberDetail::where("ptId", $id)->get();
        $ios_device_tokens = $android_device_tokens = $notification_array = array();
        foreach (count($member_details) > 0 ? $member_details : array() as $m) {
            if (isset($m->memberData)) {
                $memberData = $m->memberData;
                if ($memberData->deviceToken != "") {
                    if ($memberData->type == intval(config('constants.device_type.ios'))) {
                        $ios_device_tokens[$memberData->id] = $memberData->deviceToken;
                    } else {
                        $android_device_tokens[$memberData->id] = $memberData->deviceToken;
                    }
                }
                array_push($notification_array, $memberData->id);
            }
        }
        $type = array_search($details->type, config('constants.type'));
        $pass_parameter["type"] = "delete";
        $pass_parameter["subtype"] = $type;
        $pass_parameter["project_id"] = $details->id;
        $pass_parameter["project_name"] = $details->name;
        $pass_parameter["parent_id"] = $details->parentId;
        $pass_parameter["no_of_tasks"] = 0;
        $parent_ids = getBaseParentId($details, array());
        $child_ids = getChildIds($details->id, array());
        if ($details->type == config("constants.type.task")) {
            $pass_parameter["all_parent_ids"] = implode(",", $parent_ids);
            $pass_parameter["all_child_ids"] = implode(",", $child_ids);
            $first_parent_id = ($details->parentProject ? getFirstParentID($details->parentProject) : 0);
            $pass_parameter["no_of_tasks"] = 0;
            $pass_parameter["first_parent_project_id"] = $first_parent_id;
        }
        if (count($ios_device_tokens) > 0) {
            sendNotificationIOS($ios_device_tokens, '', '', array(), '', $pass_parameter, $id, 1);
        }
        if (count($android_device_tokens) > 0) {
            sendNotificationAndroid($android_device_tokens, '', '', array(), '', $pass_parameter, $id, 1);
        }
        $project_id = ($details->type == config("constants.type.project") ? ($details->parentId != 0 ? $details->parentId : "") : $details->parentId);
        foreach (count($notification_array) > 0 ? $notification_array : array() as $n) {
            $pusher = getPusherObject();
            $data = array("user_id" => $n, "text" => "", "type" => "delete_project_task", "pt_id" => $project_id, "slient_msg" => $details->name . " has been deleted", "is_same_user" => (Auth::user()->id == $n ? 1 : 0), "all_ids" => implode(",", array_merge($parent_ids, $child_ids)));
            $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($n), 'App\\Events\\ProjectEvent', $data);
        }
    }

    /**
     *  Recursive method delete child tasks/sub projects
     */

    private function recursiveDelete($request, $project)
    {
        $first_level_child = ProjectTaskDetail::where('parentId', $project->id)->get();
        foreach (count($first_level_child) > 0 ? $first_level_child : array() as $f) {
            $this->recursiveDelete($request, $f);
        }
        $this->deletechilds($request, $project);
    }

    /**
     *  Method to delete comment/notification/document of project/task
     */

    private function deletechilds($request, $data)
    {
        $id = $data->id;
        $comments = CommentDetail::where('pt_id', $id)->get();
        foreach (count($comments) > 0 ? $comments : array() as $c) {
            $this->deleteComment($request, $c->id);
        }

        $documents = DocumentDetail::where('ptId', $id)->get();
        foreach (count($documents) > 0 ? $documents : array() as $d) {
            $this->deleteDocument($request, $d->id);
        }

        $invitations = InvitationDetail::where('ptId', $id)->get();
        foreach (count($invitations) > 0 ? $invitations : array() as $i) {
            $i->delete();
        }

        $members = MemberDetail::where('ptId', $id)->get();
        foreach (count($members) > 0 ? $members : array() as $m) {
            $m->delete();
        }
        $notifications = NotificationDetail::where('ptId', $id)->get();
        foreach (count($notifications) > 0 ? $notifications : array() as $n) {
            $n->delete();
        }
        $statusLogs = StatusLogDetail::where('ptId', $id)->get();
        foreach (count($statusLogs) > 0 ? $statusLogs : array() as $s) {
            $s->delete();
        }
        $folderPath = public_path("uploads/$id");
        File::deleteDirectory($folderPath);
        $order = $data->ptOrder;
        $parent_id = $data->parentId;
        $first_parent_id = ($data->parentProject ? getFirstParentID($data->parentProject) : 0);
        $project_data = $data;
        $data->delete();
        if (intval($parent_id) != 0) {
            $childs = ProjectTaskDetail::where("parentId", $parent_id)->where("ptOrder", ">", $order)->get();
            foreach (count($childs) > 0 ? $childs : array() as $c) {
                $c->ptOrder = $order++;
                $c->save();
            }
        } else if ($project_data->type == config("constants.type.task")) {
            $childs = ProjectTaskDetail::where("parentId", $parent_id)->where("type", config("constants.type.task"))->where("createdBy", Auth::user()->id)->where("ptOrder", ">", $order)->get();
            foreach (count($childs) > 0 ? $childs : array() as $c) {
                $c->ptOrder = $order++;
                $c->save();
            }
        }
        sendReorderNotification($first_parent_id, $project_data, 0);
    }

    /**
     *  Method to get project details
     */

    public function getProjectDetails(Request $request)
    {
        try {
            $validator = Validator::make(
                $request->all(),
                ['projectId' => 'required'],
                ['projectId.required' => 'Please select project']
            );
            if ($validator->fails()) {
                return json_encode(array(
                    'error_msg' => $validator->errors()->first(),
                    'response' => ''
                ));
            }
            $id = $request->input('projectId');
            $details = ProjectTaskDetail::where('id', $id)->first();
            if (!$details) {
                return json_encode(array(
                    'error_msg' => 'Project not found',
                    'response' => ''
                ));
            }
            $member_details = MemberDetail::where("ptId", $id)->where("memberId", "!=", Auth::user()->id)->get();
            $member_emails = array();
            foreach (count($member_details) > 0 ? $member_details : array() as $m) {
                array_push($member_emails, $m->memberData->email);
            }
            $invitation_sent = InvitationDetail::select("memberEmailID")->where("ptId", $id)->where("status", config("constants.invitation_status")["pending"])->get();
            foreach (count($invitation_sent) > 0 ? $invitation_sent : array() as $i) {
                array_push($member_emails, $i->memberEmailID);
            }
            $document_details = DocumentDetail::where("ptId", $id)->get();
            $documents = array();
            foreach (count($document_details) > 0 ? $document_details : array() as $d) {
                array_push($documents, array("id" => $d->id, "name" => splitDocumentName($d->original_name), "url" => asset('uploads') . '/' . $d->ptId . '/' . $d->formatted_name));
            }
            $tags = getTagsName($details->tags);
            $result = array("projectname" => $details->name, "flag" => (string)$details->flag, "date" => ($details->dueDate != "" ? ($details->dueDate . " " . ($details->dueDateTime != "" ? $details->dueDateTime : "")) : ""), "repeat" => $details->repeat, "frequency" => "", "frequency_count" => "", "reminder" => ($details->reminder != "" ? $details->reminder : "None"), "projectcolor" => $details->color, "parentproject" => (string)$details->parentId, "status" => $details->status, "note" => ($details->note != null ? $details->note : ""), "tags" => ($tags != null ? explode(",", $tags) : array()), "members" => $member_emails);
            $repeat = $details->repeat;
            if ($repeat == "") {
                $result['repeat'] = "Never";
            } else if ($repeat != "Never" && $repeat != "Every Day" && $repeat != "Every Week" && $repeat != "Every 2 Weeks" && $repeat != "Every Month") {
                $pt_repeat_array = explode(" ", $repeat);
                if (count($pt_repeat_array) > 0) {
                    unset($pt_repeat_array[0]);
                    $frequency = "";
                    if (count($pt_repeat_array) == 1) {
                        $result['frequency_count'] = "1";
                        $frequency = strtolower($pt_repeat_array[1]);
                    } else {
                        $result['frequency_count'] = (string)$pt_repeat_array[1];
                        $frequency = strtolower($pt_repeat_array[2]);
                    }
                    if ($frequency == "days" || $frequency == "day")
                        $result['frequency'] = "daily";
                    else if ($frequency == "weeks" || $frequency == "week")
                        $result['frequency'] = "weekly";
                    else if ($frequency == "months" || $frequency == "month")
                        $result['frequency'] = "monthly";
                    else if ($frequency == "years" || $frequency == "year")
                        $result['frequency'] = "yearly";
                }
            }
            if ($details->type == config("constants.type.project")) {
                $result["status_name"] = array_search($details->status, config("constants.project_status"));
                $result["parent_project_status"] = $details->parentProject ? array_search($details->parentProject->status, config("constants.project_status")) : "";
            }

            $result['files'] = $documents;
            return json_encode(array(
                'error_msg' => '',
                'response' => $result
            ));
        } catch (\Exception $e) {
            return json_encode(array(
                'error_msg' => $e->getMessage(),
                'response' => ''
            ));
        }
    }

    /**
     *  Method to Complete task
     */

    public function completeTask(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "task_id" => 'required',
                "status" => 'required'
            ], [
                "task_id.required" => "Please select task",
                "status.required" => "Please enter status"
            ]);
            if ($validator->fails()) {
                return json_encode(array(
                    'error_msg' => $validator->errors()->first(),
                    'response' => ''
                ));
            }
            $task_id = $request->input("task_id");
            $task_data = ProjectTaskDetail::where("id", $task_id)->where("type", config("constants.type.task"))->first();
            if (!$task_data) {
                return json_encode(array(
                    'error_msg' => "Task not found",
                    'response' => ''
                ));
            }
            $status = $request->input("status");
            $new_status = ($status == "completed" ? config("constants.project_status.completed") : config("constants.project_status.active"));
            $task_data->status = $new_status;
            $task_data->save();

            $userid = Auth::user()->id;
            $is_repeated = 0;
            if ($status == "completed" && $task_data->repeat != "Never" && $task_data->repeat != "") {
                $result = repeatChildProjectTask($task_data, $task_data->repeat, 1);
                if ($result) {
                    $is_repeated = 1;
                }
            }
            sendToReviewProject($task_data);
            $member_details = MemberDetail::where("ptId", $task_id)->get();
            $ios_device_tokens = $web_notification_array = $android_device_tokens = $all_member_details = $remaining_users = $remaining_users_token = array();
            foreach (count($member_details) > 0 ? $member_details : array() as $m) {
                if ($m->memberId == $userid) {
                    $userData = $m->memberData;
                    array_push($remaining_users, $userData->id);
                    if ($userData->deviceToken != "" && $userData->type == intval(config('constants.device_type.ios'))) {
                        $remaining_users_token[$m->memberId] = $userData->deviceToken;
                    }
                    continue;
                }
                if (isset($m->memberData)) {
                    $memberData = $m->memberData;
                    $permission = array("email" => 1, "push_notification" => 1);
                    $member_permission = NotificationSettingDetail::where("userId", $memberData->id)->where("notificationType", ("task_" . $status))->first();
                    if ($member_permission) {
                        $permission["email"] = $member_permission->email;
                        $permission["push_notification"] = $member_permission->pushNotification;
                    }

                    if ($permission["push_notification"]) {
                        if ($memberData->deviceToken != "") {
                            if ($memberData->type == intval(config('constants.device_type.ios'))) {
                                $ios_device_tokens[$memberData->id] = $memberData->deviceToken;
                            } else {
                                $android_device_tokens[$memberData->id] = $memberData->deviceToken;
                            }
                        }
                        array_push($web_notification_array, $memberData->id);
                    } else {
                        $remaining_users_token[$memberData->id] = $memberData->deviceToken;
                    }
                    if ($permission["email"]) {
                        $all_member_details[$memberData->id] = $memberData;
                    }
                }
            }
            $all_parent_ids = getBaseParentId($task_data, array());
            $all_child_ids = getChildIds($task_id, array());
            $childs = implode(",", array_merge($all_parent_ids, $all_child_ids));
            $pass_parameter["type"] = $status;
            $pass_parameter["subtype"] = "task";
            $pass_parameter["task_id"] = $task_id;
            $pass_parameter["project_id"] = intval($task_data->parentId);
            $parent_project_name = ($task_data->parentProject ? $task_data->parentProject->name : "");
            $pass_parameter["project_name"] = $parent_project_name;
            $pass_parameter["project_status"] = ($task_data->parentProject ? $task_data->parentProject->status : 1);
            $pass_parameter["is_repeated"] = $is_repeated;
            $pass_parameter["all_parent_ids"] = implode(",", $all_parent_ids);
            $pass_parameter["all_child_ids"] = implode(",", $all_child_ids);
            $first_parent_id = ($task_data->parentProject ? getFirstParentID($task_data->parentProject) : 0);
            $pass_parameter["no_of_tasks"] = 0;
            $pass_parameter["first_parent_project_id"] = $first_parent_id;

            $notification_text = config("notificationText.complete_uncomplete");
            $notification_text = str_replace(array("{user}", "{action}", "{project_name}"), array(Auth::user()->name, ($status == "completed" ? "completed" : "incompleted"), $parent_project_name), $notification_text);

            foreach (count($all_member_details) > 0 ? $all_member_details : array() as $key => $value) {
                Notification::send($value, new MailNotification(array('text' => $notification_text, 'subtext' => ($task_data->name . ($parent_project_name != "" ? (" . " . $parent_project_name) : "")), 'btntext' => 'View this task', 'subject' => ("Re: " . ($parent_project_name != "" ? $parent_project_name : $task_data->name) . " | Complete status"))));
            }
            $notification_text .= " : " . $task_data->name;
            if (count($ios_device_tokens) > 0) {
                sendNotificationIOS($ios_device_tokens, $notification_text, config("notificationText.complete_uncomplete"), array("action" => $status), "complete_uncomplete", $pass_parameter, $task_id);
            }
            if (count($android_device_tokens) > 0) {
                sendNotificationAndroid($android_device_tokens, $notification_text, config("notificationText.complete_uncomplete"), array("action" => $status), "complete_uncomplete", $pass_parameter, $task_id);
            }
            foreach (count($web_notification_array) > 0 ? $web_notification_array : array() as $key) {
                if (!isset($ios_device_tokens[$key]) && !isset($android_device_tokens[$key])) {
                    $notification_data = new NotificationDetail();
                    $notification_data->notificationType = "complete_uncomplete";
                    $notification_data->notificationText = config("notificationText.complete_uncomplete");
                    $notification_data->sentTime = Carbon::now()->format('Y-m-d h:i:s');
                    $notification_data->sentBy = Auth::user()->id;
                    $notification_data->sentTo = $key;
                    $notification_data->parameters = json_encode(array("action" => $status));
                    $notification_data->ptId = $task_id;
                    $notification_data->created_at = Carbon::now()->format('Y-m-d h:i:s');
                    $notification_data->updated_at = Carbon::now()->format('Y-m-d h:i:s');
                    $notification_data->save();
                }
                $pusher = getPusherObject();
                $data = array("user_id" => $key, "text" => $notification_text, "type" => "complete_uncomplete", "pt_id" => $task_data->parentId, "all_ids" => $childs);
                $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($key), 'App\\Events\\ProjectEvent', $data);
            }
            if (count($remaining_users_token) > 0) {
                sendSlientNotificationIOS($remaining_users_token, $pass_parameter);
            }
            foreach ($remaining_users as $ru) {
                $pusher = getPusherObject();
                $data = array("user_id" => $ru, "text" => "", "type" => "refresh_list", "pt_id" => $task_data->parentId, "all_ids" => $childs);
                $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($ru), 'App\\Events\\ProjectEvent', $data);
            }
            return json_encode(array(
                'error_msg' => "",
                'response' => "Task " . ($status == "completed" ? "completed" : "incompleted") . " successfully"
            ));
        } catch (\Exception $e) {
            return json_encode(array(
                'error_msg' => $e->getMessage(),
                'response' => ''
            ));
        }
    }

    /**
     *  Method to assign member to task
     */

    public function assignMemberTask(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "task_id" => 'required',
            ], [
                "task_id.required" => "Please enter task id",
            ]);
            if ($validator->fails()) {
                return json_encode(array(
                    'error_msg' => $validator->errors()->first(),
                    'response' => ''
                ));
            }
            $task_id = $request->input("task_id");
            $task_details = ProjectTaskDetail::where("id", $task_id)->first();
            if (!$task_details) {
                return json_encode(array(
                    'error_msg' => "Task not found",
                    'response' => ''
                ));
            }
            $this->assignMembers($request, $task_details);
            return json_encode(array(
                'error_msg' => "",
                'response' => "Member(s) assigned successfully"
            ));
        } catch (\Exception $e) {
            return json_encode(array(
                'error_msg' => $e->getMessage(),
                'response' => ""
            ));
        }
    }

    /**
     *  Method to Complete project
     */

    public function completeProject(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "project_id" => 'required'
            ], [
                "project_id.required" => "Please select project"
            ]);
            if ($validator->fails()) {
                return json_encode(array(
                    'error_msg' => $validator->errors()->first(),
                    'response' => ''
                ));
            }
            $project_id = $request->input("project_id");
            $project_data = ProjectTaskDetail::where("id", $project_id)->where("type", config("constants.type.project"))->first();
            if (!$project_data) {
                return json_encode(array(
                    'error_msg' => "Project not found",
                    'response' => ''
                ));
            }
            $count = $this->checkStatusOfChilds($project_id, 0);
            if ($count != 0) {
                return json_encode(array(
                    'error_msg' => "Sub projects/Tasks not completed",
                    'response' => ''
                ));
            }
            $project_data->status = config("constants.project_status.completed");
            $project_data->save();

            $is_repeated = 0;
            if ($project_data->repeat != "Never" && $project_data->repeat != "") {
                $new_project = repeatChildProjectTask($project_data, $project_data->repeat, 1);
                if ($new_project) {
                    $is_repeated = 1;
                }
            }
            sendToReviewProject($project_data);
            $this->sendNotificationForCompleteProject($project_data, $is_repeated);
            return json_encode(array(
                'error_msg' => "",
                'response' => "Project completed successfully"
            ));
        } catch (\Exception $e) {
            return json_encode(array(
                'error_msg' => $e->getMessage(),
                'response' => ''
            ));
        }
    }

    /**
     *  Method to send notification when complete project
     */

    private function sendNotificationForCompleteProject($project_data, $is_repeated, $is_update = 0)
    {
        $userid = Auth::user()->id;
        $project_id = $project_data->id;
        $member_details = MemberDetail::where("ptId", $project_id)->get();
        $ios_device_tokens = $web_notification_array = $android_device_tokens = $all_member_details = $remaining_users = $remaining_users_token = array();
        foreach (count($member_details) > 0 ? $member_details : array() as $m) {
            if ($m->memberId == $userid) {
                $userData = $m->memberData;
                array_push($remaining_users, $userData->id);
                if ($userData->deviceToken != "" && $userData->type == intval(config('constants.device_type.ios'))) {
                    $remaining_users_token[$m->memberId] = $userData->deviceToken;
                }
                continue;
            }
            if (isset($m->memberData)) {
                $memberData = $m->memberData;
                $permission = array("email" => 1, "push_notification" => 1);
                $member_permission = NotificationSettingDetail::where("userId", $memberData->id)->where("notificationType", "project_completed")->first();
                if ($member_permission) {
                    $permission["email"] = $member_permission->email;
                    $permission["push_notification"] = $member_permission->pushNotification;
                }

                if ($permission["push_notification"]) {
                    if ($memberData->deviceToken != "") {
                        if ($memberData->type == intval(config('constants.device_type.ios'))) {
                            $ios_device_tokens[$memberData->id] = $memberData->deviceToken;
                        } else {
                            $android_device_tokens[$memberData->id] = $memberData->deviceToken;
                        }
                    }
                    array_push($web_notification_array, $memberData->id);
                } else {
                    $remaining_users_token[$memberData->id] = $memberData->deviceToken;
                }
                if ($permission["email"]) {
                    $all_member_details[$memberData->id] = $memberData;
                }
            }
        }
        $parent_ids = getBaseParentId($project_data, array());
        $child_ids = getChildIds($project_data->id, array());
        $childs = implode(",", array_merge($parent_ids, $child_ids));
        $pass_parameter["type"] = "completed";
        $pass_parameter["subtype"] = "project";
        $pass_parameter["project_id"] = $project_data->id;
        $pass_parameter["project_name"] = $project_data->name;
        $pass_parameter["project_status"] = $project_data->status;
        $pass_parameter["is_repeated"] = $is_repeated;
        $pass_parameter["parent_id"] = intval($project_data->parentId);
        $pass_parameter["level"] = $project_data->parentLevel;
        $pass_parameter["parent_status"] = ($project_data->parentProject ? $project_data->parentProject->status : 1);
        $pass_parameter["due_date"] = getDueDateTime($project_data->dueDate, $project_data->dueDateTime);
        $pass_parameter["all_parent_ids"] = implode(",", $parent_ids);
        $pass_parameter["all_child_ids"] = implode(",", $child_ids);

        $notification_text = config("notificationText.complete_project");
        $notification_text = str_replace(array("{user}"), array(Auth::user()->name), $notification_text);

        foreach (count($all_member_details) > 0 ? $all_member_details : array() as $key => $value) {
            Notification::send($value, new MailNotification(array('text' => $notification_text, 'subtext' => $project_data->name, 'btntext' => 'View this project', 'subject' => ("Re: " . $project_data->name) . " | Complete status")));
        }
        $notification_text .= " : " . $project_data->name;
        if (count($ios_device_tokens) > 0) {
            sendNotificationIOS($ios_device_tokens, $notification_text, config("notificationText.complete_project"), array(), "complete_project", $pass_parameter, $project_id);
        }
        if (count($android_device_tokens) > 0) {
            sendNotificationAndroid($android_device_tokens, $notification_text, config("notificationText.complete_project"), array(), "complete_project", $pass_parameter, $project_id);
        }
        foreach (count($web_notification_array) > 0 ? $web_notification_array : array() as $key) {
            if (!isset($ios_device_tokens[$key]) && !isset($android_device_tokens[$key])) {
                $notification_data = new NotificationDetail();
                $notification_data->notificationType = "complete_project";
                $notification_data->notificationText = config("notificationText.complete_project");
                $notification_data->sentTime = Carbon::now()->format('Y-m-d h:i:s');
                $notification_data->sentBy = $userid;
                $notification_data->sentTo = $key;
                $notification_data->parameters = json_encode(array());
                $notification_data->ptId = $project_id;
                $notification_data->created_at = Carbon::now()->format('Y-m-d h:i:s');
                $notification_data->updated_at = Carbon::now()->format('Y-m-d h:i:s');
                $notification_data->save();
            }
            $pusher = getPusherObject();
            $data = array("user_id" => $key, "text" => $notification_text, "type" => "complete_project", "pt_id" => $project_id, "all_ids" => $childs);
            $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($key), 'App\\Events\\ProjectEvent', $data);
        }

        if (count($remaining_users_token) > 0) {
            sendSlientNotificationIOS($remaining_users_token, $pass_parameter);
        }
        if (!$is_update) {
            foreach ($remaining_users as $ru) {
                $pusher = getPusherObject();
                $data = array("user_id" => $ru, "text" => "", "type" => "refresh_list", "pt_id" => $project_id, "all_ids" => $childs);
                $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($ru), 'App\\Events\\ProjectEvent', $data);
            }
        }
    }

    /**
     * Method to delete user account
     */
    public function deleteAccount(Request $request)
    {
        try {
            $user = Auth::user();
            $user_id = $user->id;
            $user_projects = MemberDetail::where("memberId", $user_id)->get();
            foreach (count($user_projects) > 0 ? $user_projects : array() as $up) {
                if ($up->projectDetail->createdBy == $user_id) {
                    $projectDetail = $up->projectDetail;
                    $this->sendNotificationDeleteProjectTask($projectDetail);
                    $this->recursiveDelete($request, $projectDetail);
                } else {
                    $up->delete();
                }
            }
            $token_details = UsersTokenDetail::where("email", $user->email)->get();
            foreach (count($token_details) > 0 ? $token_details : array() as $td) {
                $td->delete();
            }
            $tags = Tags::where("userId", $user_id)->get();
            foreach (count($tags) > 0 ? $tags : array() as $t) {
                $t->delete();
            }
            $notification_setting = NotificationSettingDetail::where("userId", $user_id)->get();
            foreach (count($notification_setting) > 0 ? $notification_setting : array() as $ns) {
                $ns->delete();
            }
            $user->delete();
            Auth::logout();
            return json_encode(array(
                'error_msg' => '',
                'response' => 'User deleted successfully'
            ));
        } catch (\Exception $e) {
            return json_encode(array(
                'error_msg' => $e->getMessage(),
                'response' => ''
            ));
        }
    }

    /**
     * Method to reorder task/project when move task/project
     */
    public function moveTaskProject(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "project_id" => 'required'
            ], [
                "project_id.required" => "Please select project"
            ]);
            if ($validator->fails()) {
                return json_encode(array(
                    'error_msg' => $validator->errors()->first(),
                    'response' => ''
                ));
            }
            $project_id = $request->input("project_id");
            $base_parent_id = $request->input("base_parent_id");

            $new_data = $request->input("reorder_data");
            $order = 1;
            foreach (count($new_data) > 0 ? $new_data : array() as $nd) {
                $detail = ProjectTaskDetail::where("id", $nd["id"])->first();
                if ($detail) {
                    if ($detail->parentId != $base_parent_id) {
                        $detail = $this->changeParent($detail, array("id" => $base_parent_id));
                    }
                    $detail->ptOrder = $order;
                    $detail->save();
                    $order = $order + 1;
                    $this->reorderChild($nd, 1);
                }
            }
            $project_data = ProjectTaskDetail::where("id", $project_id)->first();
            if (!$project_data) {
                return json_encode(array(
                    'error_msg' => "Project not found",
                    'response' => ''
                ));
            }
            $selected_value = $request->input("selected_value");
            if ($selected_value == "parent_member") {
                $get_parent_member = getBaseParentId($project_data, array());
                $existing_member = MemberDetail::where("ptId", $project_data->id)->get();
                foreach (count($get_parent_member) > 0 ? $get_parent_member : array() as $pm) {
                    foreach (count($existing_member) > 0 ? $existing_member : array() as $m) {
                        $is_member = MemberDetail::where("ptId", $pm)->where("memberId", $m->memberId)->count();
                        if ($is_member == 0) {
                            $member = new MemberDetail();
                            $member->ptId = $pm;
                            $member->memberId = $m->memberId;
                            $member->save();
                        }
                    }
                }
            } else if ($selected_value == "remove_member") {
                MemberDetail::where("ptId", $project_data->id)->where("memberId", "!=", $project_data->createdBy)->delete();
            }
            sendReorderNotification($project_data->parentId, $project_data);

            return json_encode(array(
                'error_msg' => '',
                'response' => 'Moved successfully'
            ));
        } catch (\Exception $e) {
            return json_encode(array(
                'error_msg' => $e->getMessage(),
                'response' => ''
            ));
        }
    }

    private function reorderChild($data, $order)
    {
        foreach (count($data["childs"]) > 0 ? $data["childs"] : array() as $dc) {
            $projectdata = ProjectTaskDetail::where("id", $dc["id"])->first();
            if ($projectdata) {
                if ($projectdata->parentId != $data["id"]) {
                    $projectdata = $this->changeParent($projectdata, $data);
                }
                $projectdata->ptOrder = $order;
                $projectdata->save();
                $order = $order + 1;
                $this->reorderChild($dc, 1);
            }
        }
    }

    private function changeParent($projectdata, $data)
    {
        $original_order = $projectdata->ptOrder;
        $old_parent_id = $projectdata->parentId;
        $projectdata->parentId = $data["id"];
        $level = 1;
        $get_parent_level = ProjectTaskDetail::selectRaw("type,parentLevel")->where("id", $data["id"])->first();
        if ($get_parent_level) {
            if ($projectdata->type == config("constants.type.task") && $get_parent_level->type == config("constants.type.project")) {
                $level = 1;
            } else {
                $level = $get_parent_level->parentLevel + 1;
            }
        }
        $projectdata->parentLevel = $level;
        $projectdata->save();
        $this->assignChildProjectLevel($projectdata->id, $level, $projectdata->type);
        $childs = ProjectTaskDetail::where("parentId", $old_parent_id)->where("ptOrder", ">", $original_order);
        if ($old_parent_id == 0) {
            $childs = $childs->where("type", config("constants.type.task"));
        }
        $childs = $childs->orderBy("ptOrder", "asc")->get();
        foreach (count($childs) > 0 ? $childs : array() as $c) {
            $c->ptOrder = $original_order++;
            $c->save();
        }
        return $projectdata;
    }
}
