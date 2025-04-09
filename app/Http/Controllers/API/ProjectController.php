<?php

namespace App\Http\Controllers\Api;

use App\Mail\InviteMember;
use App\Models\CommentDetail;
use App\Models\DocumentDetail;
use App\Models\InvitationDetail;
use App\Models\NotificationSettingDetail;
use App\Models\StatusLogDetail;
use App\Models\UserDetail;
use App\Models\MemberDetail;
use App\Models\NotificationDetail;
use App\Models\Tags;
use App\Models\UsersTokenDetail;
use App\Notifications\MailNotification;
use Carbon\Carbon;
use Notification;
use Illuminate\Support\Facades\Mail;
use Validator;
use Illuminate\Http\Request;
use App\Models\ProjectTaskDetail;
use Illuminate\Support\Facades\File;


class ProjectController extends Controller
{
    /**
     *  Method to create project
     *
     */
    public function createProjectTask(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $userId = session("user_details")->id;
            $validator = Validator::make($request->all(), [
                "name" => "required",
                "type" => "required"
            ], [
                "name.required" => "Please enter project name",
                "type.required" => "Please enter type"
            ]);

            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $project_obj = new ProjectTaskDetail();
            $project_obj->name = $request->input("name");
            if ($request->input("dueDate")) {
                $project_obj->dueDate = Carbon::parse($request->input("dueDate"))->format("Y-m-d");
                if ($request->input("dueDateTime")) {
                    $project_obj->dueDateTime = $request->input("dueDateTime");
                }
            }

            $project_obj->flag = $request->input("flag") ?? 1;
            if ($request->input("projectColor")) {
                $project_obj->color = $request->input("projectColor");
            }
            if ($request->input("note")) {
                $project_obj->note = $request->input("note");
            }
            $project_obj->repeat = $request->input("repeat") ?? "Never";
            $project_obj->reminder = $request->input("reminder") ?? "None";

            $types = config("constants.type");
            $project_obj->type = $types[$request->input("type")];
            $project_obj->parentId = $request->input("parentId") ?? 0;
            $project_obj->parentLevel = $request->input("parentLevel") ?? 1;
            refreshOrder($project_obj->parentId, 1, $project_obj->type, $userId);
            $project_obj->ptOrder = 1;
            $project_obj->status = $request->input("projectStatus") ?? 1;
            $project_obj->createdBy = $userId;
            $project_obj->updatedBy = $userId;
            $project_obj->save();

            $project_id = $project_obj->id;
            $tags = $this->addtag($request, $project_id);

            //Create project folder
            $destination_path = public_path("uploads/$project_id");
            if (!File::exists($destination_path)) {
                File::makeDirectory($destination_path);
            }
            //Add user as project member
            $project_member = new MemberDetail();
            $project_member->ptId = $project_id;
            $project_member->memberId = $userId;
            $project_member->save();

            //Upload project document
            $this->addDocument($request, $project_id);

            //Add members
            $this->sendInvitation($request, $project_id);

            //Add comments
            $this->addComment($request, $project_id);
            sendReorderNotification($project_obj->parentId, $project_obj);
            return $this->sendResultJSON("1", (ucfirst($request->input("type")) . " created"), array("id" => $project_id, "tags" => $tags));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method to add tag
     *
     */
    public function addtag(Request $request, $ptid = 0)
    {
        $isPostRequest = 0;
        if ($ptid == 0) {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            if ($request->input("ptId")) {
                $ptid = $request->input("ptId");
            }
            $isPostRequest = 1;
        }
        $userId = session("user_details")->id;
        $name = $request->input('tagName');
        $ids = $request->input('tagIds');
        $tag_ids = array();
        $added_tags = array();
        if ($name != NULL) {
            $tag_names = explode(",", $name);
            $tag_names = array_filter($tag_names);
            foreach (count($tag_names) > 0 ? $tag_names : array() as $t) {
                if (trim($t) == "") {
                    continue;
                }
                $tag_detail = Tags::where("userId", $userId)->where("tagName", $t)->first();
                if (!$tag_detail) {
                    $newtag = new Tags();
                    $newtag->userId = $userId;
                    $newtag->tagName = $t;
                    $newtag->save();
                    array_push($tag_ids, $newtag->id);
                    array_push($added_tags, array("id" => $newtag->id, "name" => $newtag->tagName));
                } else {
                    array_push($tag_ids, $tag_detail->id);
                }
            }
        }
        if ($ptid != 0) {
            if ($ids != NULL) {
                $temp_ids = explode(",", $ids);
                $tag_ids = array_merge($tag_ids, $temp_ids);
            }
            $project_detail = ProjectTaskDetail::where("id", $ptid)->first();
            if ($project_detail) {
                $project_detail->tags = (count($tag_ids) > 0 ? implode(",", $tag_ids) : "");
                $project_detail->save();

                return ($isPostRequest) ? $this->sendResultJSON("1", "Tag(s) added", array("tags" => $added_tags)) : $added_tags;
            }
            if ($isPostRequest) {
                return $this->sendResultJSON("0", "Project/Task not found");
            }
        } else {
            return ($isPostRequest) ? $this->sendResultJSON("1", "Tag(s) added", array("tags" => $added_tags)) : $added_tags;
        }
    }

    /**
     *  Method to add document
     *
     */

    public function addDocument(Request $request, $ptid = 0)
    {
        try {
            $file = $_FILES;
            $isPostRequest = 0;
            $project_detail = "";
            if ($ptid == 0) {
                if (!session("user_details")) {
                    return $this->sendResultJSON("11", "Unauthorised");
                }

                $validator = Validator::make($request->all(), [
                    "ptId" => "required",
                    "file.*" => "required"
                ], [
                    "ptId.required" => "Please select project",
                    "file.*.required" => "Please upload project document"
                ]);
                if ($validator->fails()) {
                    return $this->sendResultJSON("2", $validator->errors()->first());
                }
                $ptid = $request->input("ptId");
                $isPostRequest = 1;
                $project_detail = ProjectTaskDetail::where("id", $ptid)->first();
                if (!$project_detail) {
                    return $this->sendResultJSON("2", "Project/task not found");
                }
            } else {
                if ($file == NULL) {
                    return "success";
                }
            }
            $document_array = array();
            foreach (count($file) > 0 ? $file : array() as $f) {
                $document = new DocumentDetail();
                $document->ptId = $ptid;
                $document->original_name = $f["name"];
                $document->size = number_format((float)($f['size'] / 1024), 2, '.', '');
                $document->type = $f['type'];
                $document->uploadedBy = session("user_details")->id;
                $document->uploadedTime = Carbon::now();
                $document->save();

                $formatted_name = splitDocumentName($f["name"]);
                $document->formatted_name = (base64_encode($document->id) . "." . pathinfo($formatted_name, PATHINFO_EXTENSION));
                $document->save();

                $destination_path = public_path("uploads/$ptid");
                if (!File::exists($destination_path)) {
                    File::makeDirectory($destination_path);
                }
                $destination_path .= "/" . $document->formatted_name;
                move_uploaded_file($f["tmp_name"], $destination_path);

                $thumbnail_path = public_path("uploads/$ptid/thumbnail");
                if (!File::exists($thumbnail_path)) {
                    File::makeDirectory($thumbnail_path);
                }
                $thumbnail_url = "";
                if (strpos($document->type, 'video') !== false) {
                    $thumbnail_url = ($document->id . ".jpg");

                    generate_video_thumbnail($destination_path, ($thumbnail_path . "/" . $thumbnail_url));
                    $document->videoThumbUrl = $thumbnail_url;
                    $document->save();
                    $thumbnail_url = asset("uploads") . "/" . $document->ptId . "/thumbnail/" . $thumbnail_url;
                }

                $base_url = asset("uploads") . "/" . $document->ptId . "/" . $document->formatted_name;
                array_push($document_array, array("id" => $document->id, "name" => splitDocumentName($document->original_name), "original_name" => $document->original_name, "size" => $document->size, "type" => getDocumentType($document->type), "baseUrl" => $base_url, "thumbUrl" => $thumbnail_url, "uploadedByName" => $document->memberData->name, "uploadedBy" => $document->uploadedBy, 'uploadedTime' => convertAttachmentDate(session('user_details')->timezone, $document->uploadedTime)));
            }
            if ($isPostRequest) {
                if ($project_detail != "") {
                    sendAttachmentNotification($project_detail, "attachment");
                }
                return $this->sendResultJSON("1", "Document(s) uploaded", array("documents" => $document_array));
            }
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method to invite members
     *
     */
    public function sendInvitation(Request $request, $ptid = 0)
    {
        try {
            $isPostRequest = 0;
            if ($ptid == 0) {
                if (!session("user_details")) {
                    return $this->sendResultJSON("11", "Unauthorised");
                }
                $validator = Validator::make($request->all(), [
                    "ptId" => "required",
                    "emails" => "required"
                ], [
                    "ptId.required" => "Please select project",
                    "emails.required" => "Please enter emails"
                ]);
                if ($validator->fails()) {
                    return $this->sendResultJSON("2", $validator->errors()->first());
                }
                $ptid = $request->input("ptId");
                $isPostRequest = 1;
            }

            $userId = session("user_details")->id;
            $emails = $request->input("emails");
            $project_detail = ProjectTaskDetail::where("id", $ptid)->first();
            if (!$project_detail) {
                if ($isPostRequest) {
                    return $this->sendResultJSON("2", "Project/task not found");
                } else {
                    return "success";
                }
            }
            $project_name = ($project_detail ? $project_detail->name : "");
            $type = array_search($project_detail->type, config('constants.type'));
            $project_members = MemberDetail::where("ptId", $ptid)->get();
            $ios_device_tokens = $android_device_tokens = $web_notification_array = $all_member_detail = $remaining_users = $remaining_user_tokens = array();
            if ($emails != "") {
                foreach (explode(',', $emails) as $email) {
                    if ($email == session("user_details")->email) {
                        continue;
                    }
                    $memberId = UserDetail::selectRaw("id,name,email,deviceToken,type")->where('email', $email)->where("isVerified", 1)->first();
                    $isExistingMember = 0;
                    $permission = array("add_member" => array("email" => 1, "push_notification" => 1), "task_assigned" => array("email" => 1, "push_notification" => 1));
                    if (count($memberId) > 0) {
                        $isExistingMember = 1;
                        $member_permission = NotificationSettingDetail::where("userId", $memberId->id)->whereIn("notificationType", array("add_member", "task_assigned"))->get();
                        foreach (count($member_permission) > 0 ? $member_permission : array() as $mp) {
                            $permission[$mp->notificationType]["email"] = $mp->email;
                            $permission[$mp->notificationType]["push_notification"] = $mp->pushNotification;
                        }
                    }
                    if (!$isExistingMember && ($type == "project")) {
                        $existing_invitation = InvitationDetail::where("memberEmailID", $email)->where("ptId", $ptid)->where("status", config("constants.invitation_status")["pending"])->count();
                        if ($existing_invitation == 0) {
                            $inviteMember = new InvitationDetail();
                            $inviteMember->ptId = $ptid;
                            $inviteMember->memberId = 0;
                            $inviteMember->memberEmailID = $email;
                            $inviteMember->sentTime = Carbon::now();
                            $inviteMember->sentBy = $userId;
                            $inviteMember->status = config("constants.invitation_status")["pending"];
                            $inviteMember->save();
                            $due_date = getDueDateTime($project_detail->dueDate, $project_detail->dueDateTime);
                            $mail_text = session("user_details")->name . "  assigned a Project to you";

                            $parent_project_name = ($project_detail->parentProject ? $project_detail->parentProject->name : "");
                            Notification::send($inviteMember, new MailNotification(array('text' => $mail_text, 'subtext' => ($project_name . ($parent_project_name != "" ? " . " . $parent_project_name : "")), 'dueDateText' => ($due_date != "" ? "Due Date : " . $due_date : ""), 'btntext' => 'View this project', 'subject' => ($project_name . " | Added you"))));
                        }
                    } else {
                        $project_member = new MemberDetail();
                        $project_member->ptId = $ptid;
                        $project_member->memberId = $memberId->id;
                        $project_member->save();
                        if ($permission[(($type == "project") ? "add_member" : "task_assigned")]["push_notification"]) {
                            if ($memberId->deviceToken != "") {
                                if ($memberId->type == intval(config('constants.device_type.ios'))) {
                                    $ios_device_tokens[$memberId->id] = $memberId->deviceToken;
                                } else {
                                    $android_device_tokens[$memberId->id] = $memberId->deviceToken;
                                }
                            }
                            array_push($web_notification_array, $memberId->id);
                        } else {
                            if ($memberId->deviceToken != "" && $memberId->type == intval(config('constants.device_type.ios'))) {
                                $remaining_user_tokens[$memberId->id] = $memberId->deviceToken;
                            }
                            array_push($remaining_users, $memberId->id);
                        }
                        if ($permission[(($type == "project") ? "add_member" : "task_assigned")]["email"]) {
                            $all_member_detail[$memberId->id] = $memberId;
                        }
                    }
                }
                $this->sendNotificationProjectTask($project_detail, $all_member_detail, $ios_device_tokens, $android_device_tokens, $web_notification_array, $remaining_user_tokens, $remaining_users);
                if ($isPostRequest) {
                    return $this->sendResultJSON("1", "Invitation sent");
                }
            }
            foreach (count($project_members) > 0 ? $project_members : array() as $pm) {
                $pusher = getPusherObject();
                $data = array("user_id" => $pm->memberId, "text" => "", "type" => "member_invited", "pt_id" => $ptid);
                $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($pm->memberId), 'App\\Events\\ProjectEvent', $data);

                $parent_project_name = ($project_detail->parentProject ? $project_detail->parentProject->name : "");
                if ($pm->memberId == $userId) {
                    if ($type == "project") {
                        $pass_parameter["type"] = "assign";
                        $pass_parameter["subtype"] = "project";
                        $pass_parameter["project_id"] = $project_detail->id;
                        $pass_parameter["project_name"] = $project_detail->name;
                        $pass_parameter["project_status"] = $project_detail->status;
                        $pass_parameter["parent_id"] = intval($project_detail->parentId);
                        $pass_parameter["all_parent_ids"] = getBaseParentId($project_detail, array(), 1);
                        $pass_parameter["all_child_ids"] = getChildIds($project_detail->id, array(), 1);
                    } else {
                        $pass_parameter["type"] = "assign";
                        $pass_parameter["subtype"] = "task";
                        $pass_parameter["task_id"] = $ptid;
                        $pass_parameter["project_id"] = intval($project_detail->parentId);
                        $pass_parameter["project_name"] = $parent_project_name;
                        $pass_parameter["project_status"] = ($project_detail->parentProject ? $project_detail->parentProject->status : 1);
                        $pass_parameter["all_parent_ids"] = getBaseParentId($project_detail, array(), 1);
                        $pass_parameter["all_child_ids"] = getChildIds($project_detail->id, array(), 1);
                        $pass_parameter["first_parent_project_id"] = ($project_detail->parentProject ? getFirstParentID($project_detail->parentProject) : 0);
                    }
                    if (session("user_details")->deviceToken != "" && session("user_details")->type == intval(config('constants.device_type.ios'))) {
                        sendSlientNotificationIOS(array(session("user_details")->id => session("user_details")->deviceToken), $pass_parameter);
                    }
                } else {
                    if ($pm->memberData) {
                        $memberData = $pm->memberData;
                        $pass_parameter["type"] = "update_member";
                        $pass_parameter["subtype"] = $type;
                        if ($type == "project") {
                            $pass_parameter["project_id"] = $project_detail->id;
                            $pass_parameter["project_name"] = $project_detail->name;
                            $pass_parameter["project_status"] = $project_detail->status;
                        } else {
                            $pass_parameter["task_id"] = $project_detail->id;
                            $pass_parameter["project_id"] = intval($project_detail->parentId);
                            $pass_parameter["project_name"] = $parent_project_name;
                            $pass_parameter["project_status"] = ($project_detail->parentProject ? $project_detail->parentProject->status : 1);
                            $pass_parameter["first_parent_project_id"] = ($project_detail->parentProject ? getFirstParentID($project_detail->parentProject) : 0);
                        }
                        $pass_parameter["members"] = getMembers($project_detail->id);
                        $members = getMemberNames($project_detail->id, $project_detail->createdBy, ($type == "project" ? 0 : 1));
                        $pass_parameter['member_names'] = $members["member_names"];
                        $pass_parameter['member_emails'] = $members["member_emails"];
                        $pass_parameter["owner_name"] = $project_detail->creatorData ? $project_detail->creatorData->name : "";
                        $pass_parameter["all_parent_ids"] = getBaseParentId($project_detail, array(), 1);
                        $pass_parameter["all_child_ids"] = getChildIds($project_detail->id, array(), 1);
                        if ($memberData->deviceToken != "" && $memberData->type == intval(config('constants.device_type.ios'))) {
                            sendSlientNotificationIOS(array($memberData->id => $memberData->deviceToken), $pass_parameter);
                        }
                    }
                }
            }
            if ($isPostRequest) {
                return $this->sendResultJSON("0", "Email-Ids not found");
            }
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method to add comment
     *
     */
    public function addComment(Request $request, $ptid = 0)
    {
        try {
            $isPostRequest = 0;
            if ($ptid == 0) {
                if (!session("user_details")) {
                    return $this->sendResultJSON("11", "Unauthorised");
                }
                $validator = Validator::make($request->all(), [
                    "ptId" => "required",
                    "comments" => "required"
                ], [
                    "ptId.required" => "Please select project",
                    "comments.required" => "Please enter comments"
                ]);
                if ($validator->fails()) {
                    return $this->sendResultJSON("0", $validator->errors()->first());
                }
                $ptid = $request->input("ptId");
                $isPostRequest = 1;
            } else {
                if (!$request->input("comments")) {
                    return "success";
                }
            }
            $project_detail = ProjectTaskDetail::where("id", $ptid)->first();
            if (!$project_detail) {
                if ($isPostRequest) {
                    return $this->sendResultJSON("2", "Project/task not found");
                } else {
                    return "success";
                }
            }
            $userid = session("user_details")->id;
            $comments = $request->input("comments");
            if (is_array(json_decode($comments))) {
                $comment_array = json_decode($comments);
                foreach (count($comment_array) > 0 ? $comment_array : array() as $c) {
                    $comment = new CommentDetail();
                    $comment->pt_id = $ptid;
                    $comment->comment = $c;
                    $parentId = $request->input("commentParentId") ?? 0;
                    $comment->parentId = $parentId;
                    $parentLevel = CommentDetail::select("parentLevel")->where("id", $parentId)->first();
                    $comment->parentLevel = ($parentLevel == null) ? 1 : (($parentLevel->parentLevel < 2) ? ($parentLevel->parentLevel + 1) : 1);
                    $comment->commentedBy = $userid;
                    $comment->commentedTime = Carbon::now()->format("Y-m-d H:i:s");
                    $comment->save();
                }
                return "success";
            }
            $comment = new CommentDetail();
            $comment->pt_id = $ptid;
            $comment->comment = $comments;
            $parentId = $request->input("commentParentId") ?? 0;
            $comment->parentId = $parentId;
            $parentLevel = CommentDetail::select("parentLevel")->where("id", $parentId)->first();
            $comment->parentLevel = (!$parentLevel) ? 1 : (($parentLevel->parentLevel < 2) ? ($parentLevel->parentLevel + 1) : 1);
            $comment->commentedBy = $userid;
            $comment->commentedTime = Carbon::now();
            $comment->save();

            $file = NULL;
            if (count($_FILES) > 0) {
                $file = $_FILES["file"];
            }
            if ($file != NULL) {
                $comment->documentName = $file['name'];
                $comment->originalName = $file['name'];
                $comment->documentSize = number_format((float)($file['size'] / 1024), 2, '.', '');
                $comment->documentType = $file['type'];
                $destination_path = public_path("uploads/$ptid/comment");
                if (!File::exists($destination_path)) {
                    File::makeDirectory($destination_path);
                }
                $destination_path .= "/" . basename($file['name']);
                move_uploaded_file($file["tmp_name"], $destination_path);

                $thumbnail_path = public_path("uploads/$ptid/comment/thumbnail");
                if (!File::exists($thumbnail_path)) {
                    File::makeDirectory($thumbnail_path);
                }
                if (strpos($comment->documentType, 'video') !== false) {
                    $thumbnail_url = ($comment->id . ".jpg");
                    generate_video_thumbnail($destination_path, ($thumbnail_path . "/" . $thumbnail_url));
                    $comment->documentThumbUrl = $thumbnail_url;
                }
                $comment->save();
            }
            if ($isPostRequest) {
                $member_details = MemberDetail::where("ptId", $ptid)->get();
                $ios_device_tokens = $android_device_tokens = $all_member_details = $web_notification_array = $remaining_users = $remaining_user_token = array();
                foreach (count($member_details) > 0 ? $member_details : array() as $m) {
                    if (isset($m->memberData)) {
                        $memberData = $m->memberData;
                        if ($memberData->id == $userid) {
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
                            array_push($web_notification_array, $memberData->id);
                        } else {
                            if ($memberData->deviceToken != "" && $memberData->type == intval(config('constants.device_type.ios'))) {
                                $remaining_user_token[$memberData->id] = $memberData->deviceToken;
                            }
                            array_push($remaining_users, $memberData->id);
                        }
                        if ($permission["email"]) {
                            $all_member_details[$memberData->id] = $memberData;
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
                $add_comment_text = str_replace(array("{commented_by}", "{name}"), array(session("user_details")->name, $project_name), $add_comment_text);

                foreach (count($all_member_details) > 0 ? $all_member_details : array() as $m) {
                    Notification::send($m, new MailNotification(array('text' => $add_comment_text, 'subtext' => $comments, 'btntext' => 'Reply', 'subject' => ("Re : " . $project_name))));
                }
                $add_comment_text .= " : " . $comments;
                if (count($ios_device_tokens) > 0) {
                    sendNotificationIOS($ios_device_tokens, $add_comment_text, config("notificationText.add_comment"), array("commented_by" => $userid, "comment_id" => $comment->id), "add_comment", $pass_parameter, $ptid);
                }
                if (count($android_device_tokens) > 0) {
                    sendNotificationAndroid($android_device_tokens, $add_comment_text, config("notificationText.add_comment"), array("commented_by" => $userid, "comment_id" => $comment->id), "add_comment", $pass_parameter, $ptid);
                }
                $project_id = ($project_detail->type == config("constants.type.project") ? $project_detail->id : $project_detail->parentId);
                $all_parent_ids = getBaseParentId($project_detail, array());
                $all_child_ids = getChildIds($project_detail->id, array());
                if (count($all_child_ids) > 0) {
                    $all_parent_ids = array_merge($all_parent_ids, $all_child_ids);
                }
                $childs = implode(",", $all_parent_ids);
                foreach (count($web_notification_array) > 0 ? $web_notification_array : array() as $n) {
                    if (!isset($ios_device_tokens[$n]) && !isset($android_device_tokens[$n])) {
                        $notification_data = new NotificationDetail();
                        $notification_data->notificationType = "add_comment";
                        $notification_data->notificationText = config("notificationText.add_comment");
                        $notification_data->sentTime = Carbon::now()->format('Y-m-d h:i:s');
                        $notification_data->sentBy = $userid;
                        $notification_data->sentTo = $n;
                        $notification_data->parameters = json_encode(array("commented_by" => $userid, "comment_id" => $comment->id));
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
            }
            if ($isPostRequest) {
                return $this->sendResultJSON("1", "Comment Added", array("comments" => array("id" => $comment->id, "comment" => $comment->comment, "parentID" => $comment->parentId, "level" => $comment->parentLevel, "documentName" => $comment->documentName, "documentSize" => $comment->documentSize, "documentType" => ($comment->documentType != "" ? getDocumentType($comment->documentType) : null), "documentURL" => ($comment->documentName != "" ? (asset("uploads") . "/" . $ptid . "/comment/" . $comment->documentName) : null), "documentThumbUrl" => ($comment->documentThumbUrl != "" ? (asset("uploads") . "/" . $ptid . "/comment/thumbnail/" . $comment->documentThumbUrl) : null), "commentedByUserId" => $comment->commentedBy, "commentedBy" => $comment->memberData->name, "commentedTime" => convertCommentDate(session('user_details')->timezone, $comment->commentedTime))));
            }
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method to edit comment
     *
     */
    public function editComment(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $validator = Validator::make($request->all(), [
                "commentId" => "required",
                "comment" => "required"
            ], [
                "commentId.required" => "Please select comment",
                "comment.required" => "Please enter comment"
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $commentId = $request->input("commentId");
            $comment = CommentDetail::where("id", $commentId)->first();
            if (!$comment) {
                return $this->sendResultJSON("2", "Comment not found");
            }
            $comment->comment = $request->input("comment");
            $comment->commentedBy = session("user_details")->id;
            $comment->commentedTime = Carbon::now();
            $comment->save();
            if (isset($comment->projectDetail)) {
                sendCommentNotification($comment->projectDetail, "edit_comment");
            }
            return $this->sendResultJSON("1", "Comment updated successfully");
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method to delete comment
     *
     */
    public function deleteComment(Request $request, $commentId = 0)
    {
        try {
            $is_private_call = 1;
            if ($commentId == 0) {
                if (!session("user_details")) {
                    return $this->sendResultJSON("11", "Unauthorised");
                }
                $validator = Validator::make($request->all(), [
                    "commentId" => "required",
                ], [
                    "commentId.required" => "Please select comment",
                ]);
                if ($validator->fails()) {
                    return $this->sendResultJSON("2", $validator->errors()->first());
                }
                $commentId = $request->input("commentId");
                $is_private_call = 0;
            }
            $comment = CommentDetail::where("id", $commentId)->first();
            if (!$comment) {
                return (!$is_private_call) ? $this->sendResultJSON("2", "Comment not found") : true;
            }

            $child_comments = CommentDetail::where("pt_id", $comment->pt_id)->where("parentId", $commentId)->get();
            foreach (count($child_comments) > 0 ? $child_comments : array() as $c) {
                if ($c->documentName != "") {
                    unlink(public_path("uploads/" . $c->pt_id . "/comment/" . $c->documentName));
                }
                if ($c->documentThumbUrl != "") {
                    unlink(public_path("uploads/" . $c->pt_id . "/comment/thumbnail/" . $c->documentThumbUrl));
                }
                $child_notifications = NotificationDetail::whereRaw('JSON_EXTRACT(parameters, "$.comment_id") = ' . $c->id)->get();
                foreach (count($child_notifications) > 0 ? $child_notifications : array() as $cn) {
                    $cn->delete();
                }
                $c->delete();
            }
            if ($comment->documentName != "") {
                unlink(public_path("uploads/" . $comment->pt_id . "/comment/" . $comment->documentName));
            }
            if ($comment->documentThumbUrl != "") {
                unlink(public_path("uploads/" . $comment->pt_id . "/comment/thumbnail/" . $comment->documentThumbUrl));
            }
            $notifications = NotificationDetail::whereRaw('JSON_EXTRACT(parameters, "$.comment_id") = ' . $commentId)->get();
            foreach (count($notifications) > 0 ? $notifications : array() as $n) {
                $n->delete();
            }
            $comment->delete();
            if (!$is_private_call && isset($comment->projectDetail)) {
                sendCommentNotification($comment->projectDetail, "delete_comment");
            }
            return (!$is_private_call) ? $this->sendResultJSON("1", "Comment(s) deleted successfully") : true;
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method to get project details
     *
     */
    public function projectTaskDetails(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $validator = Validator::make($request->all(), [
                "ptId" => "required"
            ], [
                "ptId.required" => "Please select project/task"
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $ptid = $request->input("ptId");
            $get_project_details = ProjectTaskDetail::where("id", $ptid)->first();
            if (!$get_project_details) {
                return $this->sendResultJSON("2", "Data not found");
            }
            $project_details = getProjectDetails($get_project_details);
            $project_members = array();
            if (array_search($get_project_details->type, config('constants.type')) == "task") {
                $project_member_details = MemberDetail::where("ptId", $get_project_details->parentId)->get();
                foreach (count($project_member_details) > 0 ? $project_member_details : array() as $m) {
                    $member_data = $m->memberData;
                    array_push($project_members, array("id" => $member_data->id, "name" => ($member_data->name ?? $member_data->email), "email" => $member_data->email));
                }
            }
            return $this->sendResultJSON("1", "", array("details" => $project_details, "all_member_details" => $project_members));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }


    /**
     *  Method to edit tag
     *
     */
    public function editTag(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $validator = Validator::make($request->all(), [
                "tagId" => "required",
                "tagName" => "required"
            ], [
                "tagId.required" => "Please enter tag id",
                "tagName.required" => "Please enter tag name"
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $tag = Tags::where("id", $request->input("tagId"))->first();
            if (!$tag) {
                return $this->sendResultJSON("2", "Tag not found");
            }
            $tag->tagName = $request->input("tagName");
            $tag->save();
            return $this->sendResultJSON("1", "Tag updated successfully");
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method to delete tag
     *
     */
    public function deleteTag(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $validator = Validator::make($request->all(), [
                "tagId" => "required"
            ], [
                "tagId.required" => "Please enter tag id"
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $tag = Tags::where("id", $request->input("tagId"))->first();
            if (!$tag) {
                return $this->sendResultJSON("2", "Tag not found");
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
            return $this->sendResultJSON("1", "Tag deleted successfully");
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }


    /**
     *  Method to update due date and time.
     *
     */
    public function updateDueDateTime(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $validator = Validator::make($request->all(), [
                "ptId" => "required"
            ], [
                "ptId.required" => "Please select project/task"
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $pt_id = $request->input("ptId");
            $project_details = ProjectTaskDetail::where("id", $pt_id)->first();
            if (!$project_details) {
                return $this->sendResultJSON("2", "Data not found");
            }
            $project_details->dueDate = ($request->input("dueDate") ? Carbon::parse($request->input("dueDate"))->format("Y-m-d") : "");
            $project_details->dueDateTime = ($request->input("dueDateTime") ? $request->input("dueDateTime") : "");

            $project_details->save();
            $formatted_due_date = getDueDateTime($project_details->dueDate, $project_details->dueDateTime);
            return $this->sendResultJSON("1", "Duedate/time updated", array("original_due_date" => $project_details->dueDate, "original_due_date_time" => $project_details->dueDateTime, "due_date" => $formatted_due_date));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }


    /**
     *  Method to update reminder.
     *
     */
    public function updateReminder(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $validator = Validator::make($request->all(), [
                "ptId" => "required",
                "reminder" => "required"
            ], [
                "ptId.required" => "Please select project/task",
                "reminder.required" => "Please select reminder"
            ]);

            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $pt_id = $request->input("ptId");
            $project_details = ProjectTaskDetail::where("id", $pt_id)->first();

            if (!$project_details) {
                return $this->sendResultJSON("2", "Data not found");
            }

            $project_details->reminder = $request->input("reminder");
            $project_details->save();

            return $this->sendResultJSON("1", "Reminder updated");
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method to update priority.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function updatePriority(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $validator = Validator::make($request->all(), [
                "ptId" => "required",
                "priority" => "required"
            ], [
                "ptId.required" => "Please select project/task",
                "priority.required" => "Please select priority"
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $pt_id = $request->input("ptId");
            $project_details = ProjectTaskDetail::where("id", $pt_id)->first();
            if (!$project_details) {
                return $this->sendResultJSON("2", "Project/Task not found");
            }
            $project_details->flag = $request->input("priority");
            $project_details->save();
            return $this->sendResultJSON("1", "Priority updated");
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method to delete document
     *
     */
    public function deleteDocument(Request $request, $document_id = 0)
    {
        try {
            $is_private_call = 1;
            if ($document_id == 0) {
                if (!session("user_details")) {
                    return $this->sendResultJSON("11", "Unauthorised");
                }
                $validator = Validator::make($request->all(), [
                    "documentId" => "required"
                ], [
                    "documentId.required" => "Please select document id"
                ]);
                if ($validator->fails()) {
                    return $this->sendResultJSON("2", $validator->errors()->first());
                }
                $is_private_call = 0;
                $document_id = $request->input("documentId");
            }
            $document_details = DocumentDetail::where("id", $document_id)->first();
            if (!$document_details) {
                return (!$is_private_call) ? $this->sendResultJSON("2", "Document not found") : true;
            }
            unlink(public_path("uploads/" . $document_details->ptId . "/" . $document_details->formatted_name));
            if ($document_details->videoThumbUrl != "") {
                unlink(public_path("uploads/" . $document_details->ptId . "/thumbnail/" . $document_details->videoThumbUrl));
            }
            $document_details->delete();
            if (!$is_private_call && isset($document_details->projectDetail)) {
                sendAttachmentNotification($document_details->projectDetail, "delete_attachment");
            }
            return (!$is_private_call) ? $this->sendResultJSON("1", "Document deleted successfully") : true;
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }


    /**
     *  Method to delete document
     *
     */
    public function deleteProjectTask(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $validator = Validator::make($request->all(), [
                "projectTaskId" => "required"
            ], [
                "projectTaskId.required" => "Please select project/task"
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $id = $request->input("projectTaskId");
            $details = ProjectTaskDetail::where("id", $id)->first();
            if (!$details) {
                return $this->sendResultJSON("2", "Project/Task not found");
            }
            $type = array_search($details->type, config('constants.type'));
            $this->sendNotificationDeleteProjectTask($details);
            $this->recursiveDelete($request, $details);
            return $this->sendResultJSON("1", "$type deleted successfully");
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    private function sendNotificationDeleteProjectTask($details)
    {
        $id = $details->id;
        $userid = session("user_details")->id;
        $member_details = MemberDetail::join("user_details", "member_details.memberId", "=", "user_details.id")->selectRaw("user_details.id,user_details.deviceToken,user_details.type")->where("member_details.ptId", $id)->get();
        $ios_device_tokens = $android_device_tokens = $web_notification_array = array();
        foreach (count($member_details) > 0 ? $member_details : array() as $m) {
            if ($m->deviceToken != "") {
                if ($m->type == intval(config('constants.device_type.ios'))) {
                    $ios_device_tokens[$m->id] = $m->deviceToken;
                } else {
                    $android_device_tokens[$m->id] = $m->deviceToken;
                }
            }
            array_push($web_notification_array, $m->id);
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
        foreach (count($web_notification_array) > 0 ? $web_notification_array : array() as $n) {
            $pusher = getPusherObject();
            $data = array("user_id" => $n, "text" => "", "type" => "delete_project_task", "pt_id" => $project_id, "slient_msg" => $details->name . " has been deleted", "is_same_user" => ($userid == $n ? 1 : 0), "all_ids" => implode(",", array_merge($parent_ids, $child_ids)));
            $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($n), 'App\\Events\\ProjectEvent', $data);
        }
    }

    private function recursiveDelete($request, $project)
    {
        $first_level_child = ProjectTaskDetail::where("parentId", $project->id)->get();
        foreach (count($first_level_child) > 0 ? $first_level_child : array() as $f) {
            $this->recursiveDelete($request, $f);
        }
        $this->deletechilds($request, $project);
    }

    private function deletechilds($request, $data)
    {
        $id = $data->id;
        $comments = CommentDetail::where("pt_id", $id)->get();
        foreach (count($comments) > 0 ? $comments : array() as $c) {
            $this->deleteComment($request, $c->id);
        }

        $documents = DocumentDetail::where("ptId", $id)->get();
        foreach (count($documents) > 0 ? $documents : array() as $d) {
            $this->deleteDocument($request, $d->id);
        }

        $invitations = InvitationDetail::where("ptId", $id)->get();
        foreach (count($invitations) > 0 ? $invitations : array() as $i) {
            $i->delete();
        }

        $members = MemberDetail::where("ptId", $id)->get();
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
        $type = $data->type;
        $project_data = $data;
        $first_parent_id = ($data->parentProject ? getFirstParentID($data->parentProject) : 0);
        $data->delete();
        if (intval($parent_id) != 0) {
            $childs = ProjectTaskDetail::where("parentId", $parent_id)->where("ptOrder", ">", $order)->get();
            foreach (count($childs) > 0 ? $childs : array() as $c) {
                $c->ptOrder = $order++;
                $c->save();
            }
        } else if ($type == config("constants.type.task")) {
            $childs = ProjectTaskDetail::where("parentId", $parent_id)->where("type", config("constants.type.task"))->where("createdBy", session("user_details")->id)->where("ptOrder", ">", $order)->get();
            foreach (count($childs) > 0 ? $childs : array() as $c) {
                $c->ptOrder = $order++;
                $c->save();
            }
        }
        sendReorderNotification($first_parent_id, $project_data);
    }

    /**
     * Update project
     */
    public function updateProject(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $validator = Validator::make($request->all(), [
                "project_id" => 'required',
                "name" => "required",
            ], [
                "project_id.required" => "Please enter project id",
                "name.required" => "Please enter project name",
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $userId = session("user_details")->id;
            $project_id = $request->input("project_id");
            $project_details = ProjectTaskDetail::where("id", $project_id)->first();
            if (!$project_details) {
                return $this->sendResultJSON("2", "Project not found");
            }
            $old_parent_id = $project_details->parentId;
            $old_status = $project_details->status;
            $old_order = $project_details->ptOrder;
            $old_data = $project_details;
            $new_status = $request->input("projectStatus") ?? 1;
            $repeat = $request->input("repeat") ?? "Never";
            $is_update_status = $request->input("isUpdateStatus") ?? 0;
            $new_project = array();
            $project_status = config("constants.project_status");
            if ($new_status == $project_status["review"]) {
                $count = $this->checkStatusOfChilds($project_details->id, 0);
                if ($count != 0) {
                    return $this->sendResultJSON("4", "Sub projects/Tasks not completed");
                }
                $project_details->send_to_review_by = $userId;
                $project_details->send_to_review_time = Carbon::now();
            } else if ($new_status == $project_status["completed"]) {
                $count = $this->checkStatusOfChilds($project_details->id, 0);
                if ($count != 0) {
                    return $this->sendResultJSON("4", "Sub projects/Tasks not completed");
                }
                if ($repeat != "Never" && $repeat != "" && ($old_status != $new_status)) {
                    $new_project = repeatChildProjectTask($project_details, $project_details->repeat);
                }
            } else {
                if (($old_status == $project_status["review"] || $old_status == $project_status["completed"]) && ($new_status == $project_status["active"] || $new_status == $project_status["on_hold"]) && $is_update_status) {
                    changeActiveStatusChilds($project_id);
                }
            }
            $project_details->name = $request->input("name");
            if (count($new_project) == 0) {
                $project_details->dueDate = "";
                $project_details->dueDateTime = "";
                if ($request->input("dueDate")) {
                    $project_details->dueDate = Carbon::parse($request->input("dueDate"))->format("Y-m-d");
                    if ($request->input("dueDateTime")) {
                        $project_details->dueDateTime = $request->input("dueDateTime");
                    }
                }
                $project_details->status = $new_status;
            }
            $project_details->flag = $request->input("flag") ?? 1;
            $project_details->color = $request->input("projectColor");
            $project_details->note = $request->input("note");
            $project_details->repeat = $repeat;
            $project_details->reminder = $request->input("reminder") ?? "None";
            $project_details->parentId = $request->input("parentId") ?? 0;
            $project_details->updatedBy = $userId;
            $project_details->tags = "";
            $project_details->parentLevel = $request->input("parentLevel");
            $project_details->save();

            $this->assignChildProjectLevel($project_id, $project_details->parentLevel, $project_details->type);
            if ($old_parent_id != $project_details->parentId) {
                refreshOrder($project_details->parentId, 1, $project_details->type, $userId);
                $project_details->ptOrder = 1;
                $project_details->save();

                refreshOldParentOrder($old_parent_id, $old_order, $project_details->type, $userId);
            }
            $tags = $this->addtag($request, $project_id);
            $this->assignMembers($request, $project_details, 1);
            $result_data = array("members" => getMembers($project_id), "due_date" => getDueDateTime($project_details->dueDate, $project_details->dueDateTime), "tags" => $tags, "parent_status" => ($new_status == $project_status["completed"] ? sendToReviewProject($project_details) : $project_status["active"]));
            if (count($new_project) > 0) {
                $result_data["repeated_project"] = $new_project;
            }
            if ($new_status == $project_status["completed"]) {
                $this->sendNotificationForCompleteProject($project_details, (count($new_project) > 0 ? 1 : 0), 1);
            }
            updateNotification($project_details, $project_id, array("new_parent" => $project_details, "isParent_changed" => ($old_parent_id != $project_details->parentId ? 1 : 0), "old_parent" => $old_data, "new_parent_data" => ($project_details->parentProject ? $project_details->parentProject : "")));
            $result_data["all_parent_ids"] = getBaseParentId($project_details, array(), 1);
            return $this->sendResultJSON("1", "Project updated successfully", $result_data);
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
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
     * Update task
     */
    public function updateTask(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $validator = Validator::make($request->all(), [
                "task_id" => 'required',
                "name" => "required",
            ], [
                "task_id.required" => "Please enter task id",
                "name.required" => "Please enter task name",
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $userId = session("user_details")->id;
            $task_id = $request->input("task_id");
            $task_details = ProjectTaskDetail::where("id", $task_id)->first();
            if (!$task_details) {
                return $this->sendResultJSON("2", "Task not found");
            }
            $old_order = $task_details->ptOrder;
            $old_parent_id = $task_details->parentId;
            $old_data = $task_details;
            $task_details->name = $request->input("name");
            $task_details->dueDate = "";
            $task_details->dueDateTime = "";
            if ($request->input("dueDate")) {
                $task_details->dueDate = Carbon::parse($request->input("dueDate"))->format("Y-m-d");
                if ($request->input("dueDateTime")) {
                    $task_details->dueDateTime = $request->input("dueDateTime");
                }
            }
            $task_details->flag = $request->input("flag") ?? 1;
            $task_details->repeat = $request->input("repeat") ?? "Never";
            $task_details->reminder = $request->input("reminder") ?? "None";
            $task_details->parentId = $request->input("parentId") ?? 0;
            $task_details->updatedBy = $userId;
            $task_details->tags = "";
            $task_details->save();

            $tags = $this->addtag($request, $task_id);
            $this->assignMembers($request, $task_details, 1);
            $members = getMemberNames($task_id, $userId, 1);
            $data = array("new_parent" => $task_details, "old_parent" => $old_data, "isParent_changed" => ($task_details->parentId != $old_parent_id ? 1 : 0), "new_parent_data" => ($task_details->parentProject ? $task_details->parentProject : ""));
            updateNotification($task_details, $task_details->parentId, $data);
            if ($task_details->parentId != $old_parent_id) {
                refreshOrder($task_details->parentId, 1, $task_details->type, $userId);
                $task_details->ptOrder = 1;
                $task_details->parentLevel = 1;
                $task_details->save();

                refreshOldParentOrder($old_parent_id, $old_order, $task_details->type, $userId);
            }
            return $this->sendResultJSON("1", "Task updated successfully", array("members" => getMembers($task_id), "original_due_date" => $task_details->dueDate, "original_due_date_time" => $task_details->dueDateTime, "due_date" => getDueDateTime($task_details->dueDate, $task_details->dueDateTime), "member_names" => $members["member_names"], "member_emails" => $members["member_emails"], "tags" => $tags));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    private function assignChildProjectLevel($project_id, $level, $type)
    {
        $first_level_child = ProjectTaskDetail::where("parentId", $project_id)->where("type", $type)->get();
        foreach (count($first_level_child) > 0 ? $first_level_child : array() as $f) {
            $f->parentLevel = $level + 1;
            $f->save();
            $this->assignChildProjectLevel($f->id, $f->parentLevel, $type);
        }
    }

    private function assignMembers($request, $project_details, $is_update = 0)
    {
        $emails = $request->input("emails");
        $is_empty_email = 0;
        $removed_member_ids = array();
        $project_members = array();
        $project_id = $project_details->id;
        $parent_project_name = ($project_details->parentProject ? $project_details->parentProject->name : "");
        $type = array_search($project_details->type, config('constants.type'));
        if ($emails == null) {
            $all_members = MemberDetail::where("memberId", "!=", session("user_details")->id)->where("ptId", $project_id)->get();
            foreach (count($all_members) > 0 ? $all_members : array() as $am) {
                array_push($removed_member_ids, $am->memberId);
            }
            $is_empty_email = 1;
        }
        $ios_device_tokens = $android_device_tokens = $all_member_detail = $existing_members = $web_notification_array = $remaining_users = $remaining_users_token = array();
        $is_added = 0;
        $base_parent_ids = getBaseParentId($project_details, array(), 1);
        $all_child_ids = getChildIds($project_id, array(), 1);
        $childs = array_merge(explode(",", $base_parent_ids), explode(",", $all_child_ids));
        $childs = implode(",", $childs);
        if (!$is_empty_email) {
            $get_project_members = MemberDetail::selectRaw("GROUP_CONCAT(memberId) as members")->where("ptId", $project_id)->first();
            if ($get_project_members) {
                $existing_members = explode(",", $get_project_members->members);
            }
            foreach (explode(',', $emails) as $email) {
                if ($email == session("user_details")->email) {
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
                        $inviteMember->sentBy = session("user_details")->id;
                        $inviteMember->status = config("constants.invitation_status")["pending"];
                        $inviteMember->save();
                        $due_date = getDueDateTime($project_details->dueDate, $project_details->dueDateTime);
                        $mail_text = session("user_details")->name . "  assigned a Project to you";

                        Notification::send($inviteMember, new MailNotification(array('text' => $mail_text, 'subtext' => ($project_details->name . ($parent_project_name != "" ? " . " . $parent_project_name : "")), 'dueDateText' => ($due_date != "" ? "Due Date : " . $due_date : ""), 'btntext' => 'View this project', 'subject' => ($project_details->name . " | Added you"))));
                        $is_added = $is_added + 1;
                    }
                }
            }
            if (count($project_members) == 0) {
                return true;
            }
            $this->sendNotificationProjectTask($project_details, $all_member_detail, $ios_device_tokens, $android_device_tokens, $web_notification_array, $remaining_users_token, $remaining_users);
            $removed_member_ids = array_diff($existing_members, array_keys($project_members));
        }
        $is_removed = 0;
        $remaining_project_members = $remaining_project_member_ids = array();
        foreach (count($removed_member_ids) > 0 ? $removed_member_ids : array() as $rm) {
            if ($rm == session("user_details")->id) {
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
                    $notification_text = str_replace(array("{removed_by}", "{name}"), array(session("user_details")->name, $project_details->name), $notification_text);
                    if ($user_detail->deviceToken != "") {
                        $pass_parameter["type"] = "removed";
                        $pass_parameter["subtype"] = $type;
                        $pass_parameter["project_id"] = $project_id;
                        $pass_parameter["project_name"] = $project_details->name;
                        $pass_parameter["all_parent_ids"] = $base_parent_ids;
                        $pass_parameter["all_child_ids"] = $all_child_ids;

                        if ($user_detail->type == intval(config('constants.device_type.ios'))) {
                            sendNotificationIOS(array($user_detail->id => $user_detail->deviceToken), $notification_text, config("notificationText.member_removed"), array("removed_by" => session("user_details")->id), "member_removed", $pass_parameter, $project_id, 1);
                        } else {
                            sendNotificationAndroid(array($user_detail->id => $user_detail->deviceToken), $notification_text, config("notificationText.member_removed"), array("removed_by" => session("user_details")->id), "member_removed", $pass_parameter, $project_id, 1);
                        }
                    } else {
                        $notification_data = new NotificationDetail();
                        $notification_data->notificationType = "member_removed";
                        $notification_data->notificationText = config("notificationText.member_removed");
                        $notification_data->sentTime = Carbon::now()->format('Y-m-d h:i:s');
                        $notification_data->sentBy = session("user_details")->id;
                        $notification_data->sentTo = $user_detail->id;
                        $notification_data->parameters = json_encode(array("removed_by" => session("user_details")->id));
                        $notification_data->created_at = Carbon::now()->format('Y-m-d h:i:s');
                        $notification_data->updated_at = Carbon::now()->format('Y-m-d h:i:s');
                        $notification_data->ptId = $project_id;
                        $notification_data->save();
                    }
                    $pusher = getPusherObject();
                    $data = array("user_id" => $user_detail->id, "text" => "", "type" => "member_removed", "pt_id" => $project_id, "slient_msg" => $notification_text);
                    $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($user_detail->id), 'App\\Events\\ProjectEvent', $data);
                    if ($permission["member_removed"]["email"]) {
                        Notification::send($user_detail, new MailNotification(array('text' => $notification_text, 'subtext' => '', 'btntext' => 'View application', 'subject' => ($project_details->name . " | Removed from project"))));
                    }

                    $remove_notification_text = config("notificationText.member_removed_by");
                    $remove_notification_text = str_replace(array("{removed_by}", "{user}", "{name}"), array(session("user_details")->name, $user_detail->name, $project_details->name), $remove_notification_text);
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
                                    sendNotificationIOS(array($pm->id => $pm->deviceToken), $remove_notification_text, config("notificationText.member_removed_by"), array("removed_by" => session("user_details")->id, "removed_member" => $user_detail->id), "member_removed_by", $pass_parameter, $project_id);
                                } else {
                                    sendNotificationAndroid(array($pm->id => $pm->deviceToken), $remove_notification_text, config("notificationText.member_removed_by"), array("removed_by" => session("user_details")->id, "removed_member" => $user_detail->id), "member_removed_by", $pass_parameter, $project_id);
                                }
                            } else {
                                $notification_data = new NotificationDetail();
                                $notification_data->notificationType = "member_removed_by";
                                $notification_data->notificationText = config("notificationText.member_removed_by");
                                $notification_data->sentTime = Carbon::now()->format('Y-m-d h:i:s');
                                $notification_data->sentBy = session("user_details")->id;
                                $notification_data->sentTo = $pm->id;
                                $notification_data->parameters = json_encode(array("removed_by" => session("user_details")->id, "removed_member" => $user_detail->id));
                                $notification_data->ptId = $project_id;
                                $notification_data->created_at = Carbon::now()->format('Y-m-d h:i:s');
                                $notification_data->updated_at = Carbon::now()->format('Y-m-d h:i:s');
                                $notification_data->save();
                            }
                            $pusher = getPusherObject();
                            $project_id = ($project_details->type == config("constants.type.project") ? $project_details->id : $project_details->parentId);
                            $data = array("user_id" => $pm->id, "text" => $remove_notification_text, "type" => "member_removed_by", "pt_id" => $project_id);
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
        $members = getMemberNames($project_id, $project_details->createdBy, ($type == "project" ? 0 : 1));
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

    private function sendNotificationProjectTask($project_detail, $all_member_detail, $ios_device_tokens, $android_device_tokens, $web_notification_array, $remaining_user_tokens, $remaining_users)
    {
        $project_name = $project_detail->name;
        $ptid = $project_detail->id;
        $userId = session("user_details")->id;
        $due_date = getDueDateTime($project_detail->dueDate, $project_detail->dueDateTime);
        $parent_ids = getBaseParentId($project_detail, array(), 1);
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
            $member_invitation_text = str_replace(array("{creator}", "{project_name}"), array(session("user_details")->name, $project_name), $member_invitation_text);

            $mail_text = session("user_details")->name . " assigned a Project to you";
            foreach (count($all_member_detail) > 0 ? $all_member_detail : array() as $key => $value) {
                Notification::send($value, new MailNotification(array('text' => $mail_text, 'subtext' => $project_name, 'dueDateText' => ($due_date != "" ? "Due Date : " . $due_date : ""), 'btntext' => 'View this project', 'subject' => ($project_name . " | Added you"))));
            }
            if (count($ios_device_tokens) > 0) {
                sendNotificationIOS($ios_device_tokens, $member_invitation_text, config("notificationText.member_invitation_create_project"), array(), "member_invitation_create_project", $pass_parameter, $ptid);
            }
            if (count($android_device_tokens) > 0) {
                sendNotificationAndroid($android_device_tokens, $member_invitation_text, config("notificationText.member_invitation_create_project"), array(), "member_invitation_create_project", $pass_parameter, $ptid);
            }
            foreach (count($web_notification_array) > 0 ? $web_notification_array : array() as $key) {
                if (!isset($ios_device_tokens[$key]) && !isset($android_device_tokens[$key])) {
                    $notification_data = new NotificationDetail();
                    $notification_data->notificationType = "member_invitation_create_project";
                    $notification_data->notificationText = config("notificationText.member_invitation_create_project");
                    $notification_data->sentTime = Carbon::now()->format('Y-m-d h:i:s');
                    $notification_data->sentBy = $userId;
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
            $create_task_text = str_replace(array("{creator}", "{project_name}"), array(session("user_details")->name, $parent_project_name), $create_task_text);

            $mail_text = session("user_details")->name . " assigned a Task to you";
            foreach (count($all_member_detail) > 0 ? $all_member_detail : array() as $key => $value) {
                Notification::send($value, new MailNotification(array('text' => $mail_text, 'subtext' => ($project_name . ($parent_project_name != "" ? " . " . $parent_project_name : "")), 'dueDateText' => ($due_date != "" ? "Due Date : " . $due_date : ""), 'btntext' => 'View this task', 'subject' => ($project_name . " | Added you"))));
            }
            $create_task_text .= " : " . $project_name;
            if (count($ios_device_tokens) > 0) {
                sendNotificationIOS($ios_device_tokens, $create_task_text, config("notificationText.create_task"), array(), "create_task", $pass_parameter, $ptid);
            }
            if (count($android_device_tokens) > 0) {
                sendNotificationAndroid($android_device_tokens, $create_task_text, config("notificationText.create_task"), array(), "create_task", $pass_parameter, $ptid);
            }
            foreach (count($web_notification_array) > 0 ? $web_notification_array : array() as $key) {
                if (!isset($ios_device_tokens[$key]) && !isset($android_device_tokens[$key])) {
                    $notification_data = new NotificationDetail();
                    $notification_data->notificationType = "create_task";
                    $notification_data->notificationText = config("notificationText.create_task");
                    $notification_data->sentTime = Carbon::now()->format('Y-m-d h:i:s');
                    $notification_data->sentBy = $userId;
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

    public function markAsReadNotification(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $validator = Validator::make($request->all(), [
                "notification_id" => 'required'
            ], [
                "notification_id.required" => "Please enter notification id"
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $data = NotificationDetail::where("id", $request->input("notification_id"))->first();
            if (!$data) {
                return $this->sendResultJSON("2", "Notification not found");
            }
            $data->isRead = 1;
            $data->save();
            return $this->sendResultJSON("1", "Notification mark as read");
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function completeTask(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $validator = Validator::make($request->all(), [
                "task_id" => 'required',
                "status" => 'required'
            ], [
                "task_id.required" => "Please select task",
                "status.required" => "Please enter status"
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $task_id = $request->input("task_id");
            $task_data = ProjectTaskDetail::where("id", $task_id)->where("type", config("constants.type.task"))->first();
            if (!$task_data) {
                return $this->sendResultJSON("2", "Task not found");
            }
            $status = $request->input("status");
            if ($status == "completed") {
                $count = $this->checkStatusOfChilds($task_id, 0);
                if ($count != 0) {
                    return $this->sendResultJSON("4", "Sub tasks not completed");
                }
            }
            $new_status = ($status == "completed" ? config("constants.project_status.completed") : config("constants.project_status.active"));
            $task_data->status = $new_status;
            $task_data->save();

            $userid = session("user_details")->id;
            $result_data = array();
            $is_repeated = 0;
            if ($status == "completed" && $task_data->repeat != "Never" && $task_data->repeat != "") {
                $new_task = repeatChildProjectTask($task_data, $task_data->repeat);
                if (count($new_task) > 0) {
                    $result_data["repeated_task"] = $new_task;
                    $is_repeated = 1;
                }
            }
            $result_data["parent_status"] = sendToReviewProject($task_data);

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
            $notification_text = str_replace(array("{user}", "{action}", "{project_name}"), array(session("user_details")->name, ($status == "completed" ? "completed" : "incompleted"), $parent_project_name), $notification_text);

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
                    $notification_data->sentBy = $userid;
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
            return $this->sendResultJSON("1", "Task " . ($status == "completed" ? "completed" : "incompleted") . " successfully", $result_data);
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function sendToReview(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $validator = Validator::make($request->all(), [
                "project_id" => 'required'
            ], [
                "project_id.required" => "Please enter project id"
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $project_details = ProjectTaskDetail::where("id", $request->input("project_id"))->first();
            if (!$project_details) {
                return $this->sendResultJSON("2", "Project not found");
            }
            if ($project_details->status == config("constants.project_status.review")) {
                return $this->sendResultJSON("2", "Project is already under review");
            }
            $count = $this->checkStatusOfChilds($project_details->id, 0);
            if ($count != 0) {
                return $this->sendResultJSON("4", "Sub projects/Tasks not completed");
            }
            $project_details->status = config("constants.project_status.review");
            $project_details->send_to_review_by = session("user_details")->id;
            $project_details->send_to_review_time = Carbon::now();
            $project_details->updatedBy = session("user_details")->id;
            $project_details->save();

            return $this->sendResultJSON("1", "Project sent to review");
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function assignMemberTask(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $validator = Validator::make($request->all(), [
                "task_id" => 'required',
            ], [
                "task_id.required" => "Please enter task id",
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $task_id = $request->input("task_id");
            $task_details = ProjectTaskDetail::where("id", $task_id)->first();
            if (!$task_details) {
                return $this->sendResultJSON("2", "Task not found");
            }
            $this->assignMembers($request, $task_details);
            $members = getMemberNames($task_id, session("user_details")->id, 1);
            return $this->sendResultJSON("1", "", array("member_names" => $members["member_names"], "member_emails" => $members["member_emails"], "creator_name" => ($task_details->creatorData ? $task_details->creatorData->name : "")));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function completeProject(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $validator = Validator::make($request->all(), [
                "project_id" => 'required'
            ], [
                "project_id.required" => "Please select project"
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $project_id = $request->input("project_id");
            $project_data = ProjectTaskDetail::where("id", $project_id)->where("type", config("constants.type.project"))->first();
            if (!$project_data) {
                return $this->sendResultJSON("2", "Project not found");
            }
            $count = $this->checkStatusOfChilds($project_id, 0);
            if ($count != 0) {
                return $this->sendResultJSON("4", "Sub projects/Tasks not completed");
            }
            $project_data->status = config("constants.project_status.completed");
            $project_data->save();

            $result_data = array();
            $is_repeated = 0;
            if ($project_data->repeat != "Never" && $project_data->repeat != "") {
                $new_project = repeatChildProjectTask($project_data, $project_data->repeat);
                if (count($new_project) > 0) {
                    $result_data["repeated_project"] = $new_project;
                    $is_repeated = 1;
                }
            }
            $result_data["parent_status"] = sendToReviewProject($project_data);
            $result_data["parent_project_id"] = $project_data->parentId;
            $this->sendNotificationForCompleteProject($project_data, $is_repeated);
            return $this->sendResultJSON("1", "Project completed successfully", $result_data);
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    private function sendNotificationForCompleteProject($project_data, $is_repeated, $is_update = 0)
    {
        $userid = session("user_details")->id;
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
        $notification_text = str_replace(array("{user}"), array(session("user_details")->name), $notification_text);

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
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $user = session("user_details");
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
            return $this->sendResultJSON("1", "User deleted successfully");
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     * Method to create sub task
     */
    public function moveTaskToParent(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $validator = Validator::make($request->all(), [
                "pt_id" => "required",
                "parent_id" => "required"
            ], [
                "pt_id.required" => "Please select project/task",
                "parent_id.required" => "Please select parent"
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $pt_data = ProjectTaskDetail::where("id", $request->input("pt_id"))->first();
            if (!$pt_data) {
                return $this->sendResultJSON("2", "Project/Task not found");
            }
            $original_order = $pt_data->ptOrder;
            $old_parent_id = intval($pt_data->parentId);
            $parent_id = intval($request->input("parent_id"));
            $old_data = $pt_data;
            if ($old_parent_id != $parent_id) {
                $pt_data->parentId = $parent_id;
                $level = 1;
                $get_parent_level = ProjectTaskDetail::selectRaw("type,parentLevel")->where("id", $parent_id)->first();
                if ($get_parent_level) {
                    if ($pt_data->type == config("constants.type.task") && $get_parent_level->type == config("constants.type.project")) {
                        $level = 1;
                    } else {
                        $level = $get_parent_level->parentLevel + 1;
                    }
                }
                $pt_data->parentLevel = $level;
                $pt_data->save();
                $this->assignChildProjectLevel($pt_data->id, $level, $pt_data->type);
                $childs = ProjectTaskDetail::where("parentId", $old_parent_id)->where("ptOrder", ">", $original_order);
                if ($old_parent_id == 0) {
                    $childs = $childs->where("type", config("constants.type.task"));
                }
                $childs = $childs->orderBy("ptOrder", "asc")->get();
                foreach (count($childs) > 0 ? $childs : array() as $c) {
                    $c->ptOrder = $original_order++;
                    $c->save();
                }
                sendReorderNotification($old_parent_id, $old_data);
            }
            $order = 1;
            $is_reordered = 0;
            if ($request->input("order")) {
                $order_para = $request->input("order");
                if (($order_para > $original_order) && ($old_parent_id == $parent_id)) {
                    $childs = ProjectTaskDetail::where("parentId", $parent_id)->where("id", "!=", $pt_data->id)->where("ptOrder", ">", $original_order);
                    if ($parent_id == 0) {
                        $childs = $childs->where("type", config("constants.type.task"));
                    }
                    $childs = $childs->orderBy("ptOrder", "asc")->get();
                    foreach (count($childs) > 0 ? $childs : array() as $c) {
                        if ($original_order == $order_para) {
                            $original_order = $original_order + 1;
                        }
                        $c->ptOrder = $original_order++;
                        $c->save();
                    }
                } else {
                    $new_order = $request->input("order");
                    $childs = ProjectTaskDetail::where("parentId", $parent_id)->where("id", "!=", $pt_data->id)->where("ptOrder", ">=", $new_order);
                    if ($parent_id == 0) {
                        $childs = $childs->where("type", config("constants.type.task"));
                    }
                    $childs = $childs->orderBy("ptOrder", "asc")->get();
                    foreach (count($childs) > 0 ? $childs : array() as $c) {
                        $new_order = $new_order + 1;
                        if ($new_order == $order_para) {
                            $new_order = $new_order + 1;
                        }
                        $c->ptOrder = $new_order;
                        $c->save();
                    }
                }
                $pt_data->ptOrder = $order_para;
                $pt_data->save();
                $is_reordered = 1;
            } else {
                refreshOrder($parent_id, $order, $pt_data->type, session("user_details")->id);
                $pt_data->ptOrder = $order;
                $pt_data->save();
                $is_reordered = 1;
            }
            $selected_value = $request->input("selected_value");
            if ($selected_value == "parent_member") {
                $get_parent_member = getBaseParentId($pt_data, array());
                $existing_member = MemberDetail::where("ptId", $pt_data->id)->get();
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
                MemberDetail::where("ptId", $pt_data->id)->where("memberId", "!=", $pt_data->createdBy)->delete();
            }
            if ($is_reordered) {
                sendReorderNotification($pt_data->parentId, $pt_data);
            }
            return $this->sendResultJSON("1", ($pt_data->type == config("constants.type.project") ? "Project" : "Task" . " moved successfully"));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }

    }
}
