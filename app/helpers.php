<?php

use App\Models\CommentDetail;
use App\Models\NotificationDetail;
use App\Models\NotificationSettingDetail;
use App\Models\DocumentDetail;
use App\Models\ProjectTaskDetail;
use App\Models\StatusLogDetail;
use App\Models\Tags;
use Carbon\Carbon;
use Pushok\AuthProvider;
use Pushok\Client;
use Pushok\Notification;
use Pushok\Payload;
use Pushok\Payload\Alert;
use App\Models\MemberDetail;
use App\Models\InvitationDetail;
use Illuminate\Support\Facades\Auth;
use Pusher\Pusher;

function generate_access_token($user_id)
{
    $token = json_encode(array(
        'user_id' => $user_id,
        'timestamp' => Carbon::Now()->timestamp
    ));
    return 'Bearer ' . base64_encode(base64_encode($token));
}

/**
 * Method to get tag names from IDs
 */
function getTagsName($tag_ids)
{
    $tags = '';
    if ($tag_ids != null) {
        $tags_array = explode(',', $tag_ids);
        if (count($tags_array) > 0) {
            $tag_details = Tags::selectRaw('GROUP_CONCAT(tagName) as tags')
                ->whereIn('id', $tags_array)
                ->first();
            if ($tag_details) {
                $tags = $tag_details->tags;
            }
        }
    }
    return $tags;
}

/**
 * Method to formated due date time
 */
function getDueDateTime($due_date, $due_time)
{
    $formatted_due_date = $formatted_due_date_time = '';
    if ($due_date != '') {
        $formatted_due_date = Carbon::parse($due_date)->format('M d');
    }
    if ($due_time != '') {
        $formatted_due_date_time = Carbon::parse($due_time)->format(
            ($due_date != '' ? ',' : '') . 'h:ia'
        );
    }
    return $formatted_due_date . $formatted_due_date_time;
}

/**
 * Method to formated due date time for edit task
 */

function getEditDueDateTime($due_date, $due_time)
{
    $formatted_due_date = $formatted_due_date_time = '';
    if ($due_date != '') {
        $formatted_due_date = ($due_date);
    }
    if ($due_time != '') {
        $formatted_due_date_time = Carbon::parse($due_time)->format(
            ' h:ia'
        );
    }
    return $formatted_due_date . $formatted_due_date_time;
}

/**
 * get document type
 */
function getDocumentType($type)
{
    $doc_type = '';
    if (strpos($type, 'image') !== false) {
        $doc_type = 'image';
    } elseif (strpos($type, 'video') !== false) {
        $doc_type = 'video';
    } else {
        $doc_type = 'document';
    }
    return $doc_type;
}

/*
 * Get user avatar
 */
function getUserAvatar($avatar)
{
    $file = '';
    if ($avatar != '' && file_exists(public_path() . '/uploads/avatar/' . $avatar)) {
        $file = asset('uploads') . '/avatar/' . $avatar;
    } else {
        $file = url('images/icon_profile.png');
    }
    return $file;
}

function getUserAvatarForEdit($avatar)
{
    $file = '';
    if ($avatar != '' && file_exists(public_path() . '/uploads/avatar/' . $avatar)) {
        $file = asset('uploads') . '/avatar/' . $avatar;
    } else {
        $file = url('images/add_profile.png');
    }
    return $file;
}

function generate_video_thumbnail($file, $thumbnail)
{
    $ffprobe = FFMpeg\FFProbe::create();

    $video = $ffprobe
        ->streams($file) // extracts streams informations
        ->videos() // filters video streams
        ->first(); // returns the first video stream
    $tags = $video->get('tags');
    if (isset($tags['rotate'])) {
        exec(
            "/usr/bin/ffmpeg -i '" .
            $file .
            "' -vf 'transpose=1' -an -ss 00:00:01 -r 1 -vframes 1 -y '" .
            $thumbnail .
            "'"
        );
    } else {
        exec(
            "/usr/bin/ffmpeg -i '" .
            $file .
            "' -an -ss 00:00:01 -r 1 -vframes 1 -y '" .
            $thumbnail .
            "'"
        );
    }
}

/**
 *  Method to send notification for IOS
 *
 * @return \Illuminate\Http\JsonResponse
 */
function sendNotificationIOS($deviceToken, $text, $originalText, $parameters, $type, $passParameter, $pt_id = 0, $isSlientNotification = 0)
{
    try {
        $options = [
            'key_id' => '2Z9J2YSV4W', // The Key ID obtained from Apple developer account
            'team_id' => '638BW42L7E', // The Team ID obtained from Apple developer account
            'app_bundle_id' => 'com.intellidt.iTask', // The bundle ID for app obtained from Apple developer account
            'private_key_path' =>
                url('notification') . '/AuthKey_2Z9J2YSV4W.p8', // Path to private key
            'private_key_secret' => null // Private key secret
        ];
        $authProvider = AuthProvider\Token::create($options);
        $notifications = [];
        $notification_database_data = array();
        foreach (count($deviceToken) > 0 ? $deviceToken : array() as $d => $value) {
            $is_insert_data = 0;
            if ($isSlientNotification) {
                $payload = Payload::create();
                $payload->setCustomValue('type', $passParameter['type']);
                $payload->setCustomValue('subtype', $passParameter['subtype']);
                $payload->setCustomValue('project_id', $passParameter['project_id']);
                $payload->setCustomValue('project_name', $passParameter['project_name']);
                if (isset($passParameter['parent_id'])) {
                    $payload->setCustomValue('parent_id', $passParameter['parent_id']);
                }
                if (isset($passParameter['project_status'])) {
                    $payload->setCustomValue('project_status', (string)$passParameter['project_status']);
                }
                if (isset($passParameter['all_parent_ids'])) {
                    $payload->setCustomValue('all_parent_ids', $passParameter['all_parent_ids']);
                }
                if (isset($passParameter['all_child_ids'])) {
                    $payload->setCustomValue('all_child_ids', $passParameter['all_child_ids']);
                }
                if (isset($passParameter['first_parent_project_id'])) {
                    $first_parent_id = $passParameter['first_parent_project_id'];
                    if (isset($passParameter['no_of_tasks'])) {
                        if ($passParameter['type'] == "delete" || $passParameter['type'] == "removed_task") {
                            $payload->setCustomValue('no_of_tasks', (getTaskTotal($first_parent_id, $d) - getTaskTotal($passParameter['project_id'], $d) - 1));
                        } else {
                            $payload->setCustomValue('no_of_tasks', getTaskTotal($first_parent_id, $d));
                        }
                    }
                    $payload->setCustomValue('first_parent_project_id', $first_parent_id);
                }
                $payload->setContentAvailability(true);
                $notifications[] = new Notification($payload, $value);
                if ($type == 'member_removed') {
                    $is_insert_data = 1;
                }
            } else {
                $alert = Alert::create();
                $alert = $alert->setBody($text);
                $payload = Payload::create()->setAlert($alert);
                $payload->setCustomValue('type', $passParameter['type']);
                $payload->setCustomValue('subtype', $passParameter['subtype']);
                $payload->setCustomValue('project_id', $passParameter['project_id']);
                $payload->setCustomValue('project_name', $passParameter['project_name']);
                if (isset($passParameter['task_id'])) {
                    $payload->setCustomValue('task_id', $passParameter['task_id']);
                }
                if (isset($passParameter['is_repeated'])) {
                    $payload->setCustomValue('is_repeated', $passParameter['is_repeated']);
                }
                if (isset($passParameter['removed_member_name'])) {
                    $payload->setCustomValue('removed_member_name', $passParameter['removed_member_name']);
                }
                if (isset($passParameter['project_status'])) {
                    $payload->setCustomValue('project_status', (string)$passParameter['project_status']);
                }
                if (isset($passParameter['level'])) {
                    $payload->setCustomValue('level', (string)$passParameter['level']);
                }
                if (isset($passParameter['parent_status'])) {
                    $payload->setCustomValue('parent_status', (string)$passParameter['parent_status']);
                }
                if (isset($passParameter['due_date'])) {
                    $payload->setCustomValue('due_date', (string)$passParameter['due_date']);
                }
                if (isset($passParameter['parent_id'])) {
                    $payload->setCustomValue('parent_id', $passParameter['parent_id']);
                }
                if (isset($passParameter['CmtCount'])) {
                    $payload->setCustomValue('CmtCount', $passParameter['CmtCount']);
                }
                if (isset($passParameter['all_parent_ids'])) {
                    $payload->setCustomValue('all_parent_ids', $passParameter['all_parent_ids']);
                }
                if (isset($passParameter['all_child_ids'])) {
                    $payload->setCustomValue('all_child_ids', $passParameter['all_child_ids']);
                }
                if (isset($passParameter['first_parent_project_id'])) {
                    $first_parent_id = $passParameter['first_parent_project_id'];
                    if (isset($passParameter['no_of_tasks'])) {
                        $payload->setCustomValue('no_of_tasks', getTaskTotal($first_parent_id, $d));
                    }
                    $payload->setCustomValue('first_parent_project_id', $first_parent_id);
                }
                $payload->setSound('default');
                $payload->setContentAvailability(true);
                $notifications[] = new Notification($payload, $value);
                if ($type != 'project_due') {
                    $is_insert_data = 1;
                }
            }
            if ($is_insert_data) {
                array_push($notification_database_data, array(
                    'notificationType' => $type,
                    'notificationText' => $originalText,
                    'sentTime' => Carbon::now()->format('Y-m-d h:i:s'),
                    'sentBy' => (session('user_details')) ? session('user_details')->id : Auth::user()->id,
                    'sentTo' => $d,
                    'parameters' => json_encode($parameters),
                    'ptId' => $pt_id,
                    'created_at' => Carbon::now()->format('Y-m-d h:i:s'),
                    'updated_at' => Carbon::now()->format('Y-m-d h:i:s')
                ));
            }
        }
        if (count($notifications) > 0) {
            if (count($notification_database_data) > 0) {
                NotificationDetail::insert($notification_database_data);
            }
            $client = new Client($authProvider, false);
            $client->addNotifications($notifications);
            $client->push();
        }
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}

/**
 *  Method to send slient notification for IOS to refresh page
 *
 * @return \Illuminate\Http\JsonResponse
 */
function sendSlientNotificationIOS($deviceToken, $passParameter)
{
    try {
        $options = [
            'key_id' => '2Z9J2YSV4W', // The Key ID obtained from Apple developer account
            'team_id' => '638BW42L7E', // The Team ID obtained from Apple developer account
            'app_bundle_id' => 'com.intellidt.iTask', // The bundle ID for app obtained from Apple developer account
            'private_key_path' =>
                url('notification') . '/AuthKey_2Z9J2YSV4W.p8', // Path to private key
            'private_key_secret' => null // Private key secret
        ];
        $authProvider = AuthProvider\Token::create($options);
        $notifications = [];
        foreach (count($deviceToken) > 0 ? $deviceToken : array() as $d => $value) {
            $payload = Payload::create();
            foreach (count($passParameter) > 0 ? $passParameter : array() as $key => $pvalue) {
                if ($key == "no_of_tasks") {
                    continue;
                }
                if ($key == "project_status" || $key == "level" || $key == "parent_status" || $key == "due_date") {
                    $payload->setCustomValue($key, (string)$pvalue);
                } else {
                    if ($key == 'first_parent_project_id') {
                        if (isset($passParameter['no_of_tasks'])) {
                            $payload->setCustomValue('no_of_tasks', getTaskTotal($pvalue, $d));
                        }
                    }
                    $payload->setCustomValue($key, $pvalue);
                }
            }
            $payload->setContentAvailability(true);
            $notifications[] = new Notification($payload, $value);
        }
        if (count($notifications) > 0) {
            $client = new Client($authProvider, false);
            $client->addNotifications($notifications);
            $client->push();
        }
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}

/**
 *  Method to send notification for android
 *
 * @return \Illuminate\Http\JsonResponse
 */
function sendNotificationAndroid($deviceToken, $text, $originalText, $parameters, $type, $passParameter, $pt_id = 0, $isSlientNotification = 0)
{
    try {
        $fcmUrl = 'https://fcm.googleapis.com/fcm/send';
        $notification_database_data = array();
        if (count($deviceToken) > 0) {
            if ((!$isSlientNotification && $type != 'project_due') || $type == 'member_removed') {
                foreach ($deviceToken as $d => $value) {
                    array_push($notification_database_data, array(
                        'notificationType' => $type,
                        'notificationText' => $originalText,
                        'sentTime' => Carbon::now()->format('Y-m-d h:i:s'),
                        'sentBy' => (session('user_details') ? session('user_details')->id : Auth::user()->id),
                        'sentTo' => $d,
                        'parameters' => json_encode($parameters),
                        'ptId' => $pt_id,
                        'created_at' => Carbon::now()->format('Y-m-d h:i:s'),
                        'updated_at' => Carbon::now()->format('Y-m-d h:i:s')
                    ));
                }
            }
            $moredata = array();
            $moredata['type'] = $passParameter['type'];
            $moredata['subtype'] = $passParameter['subtype'];
            $moredata['project_id'] = $passParameter['project_id'];
            $moredata['project_name'] = $passParameter['project_name'];
            if (isset($passParameter['parent_id'])) {
                $moredata['parent_id'] = $passParameter['parent_id'];
            }
            if (isset($passParameter['removed_member_name'])) {
                $moredata['removed_member_name'] = $passParameter['removed_member_name'];
            }
            if (isset($passParameter['task_id'])) {
                $moredata['task_id'] = $passParameter['task_id'];
            }
            if (isset($passParameter['is_repeated'])) {
                $moredata['is_repeated'] = $passParameter['is_repeated'];
            }
            if (isset($passParameter['project_status'])) {
                $moredata['project_status'] = (string)$passParameter['project_status'];
            }
            if (isset($passParameter['level'])) {
                $moredata['level'] = (string)$passParameter['level'];
            }
            if (isset($passParameter['parent_status'])) {
                $moredata['parent_status'] = (string)$passParameter['parent_status'];
            }
            if (isset($passParameter['due_date'])) {
                $moredata['due_date'] = (string)$passParameter['due_date'];
            }
            if (!$isSlientNotification) {
                $extraNotificationData = [
                    'message' => ['heading' => $text],
                    'moredata' => $moredata
                ];
            } else {
                $extraNotificationData = ['moredata' => $moredata];
            }
            $fcmNotification = [
                'registration_ids' => array_values($deviceToken),
                'data' => $extraNotificationData
            ];
            $headers = [
                'Authorization: key=AIzaSyDDDXqbsr9UaK9K7OUtNR_pH-g3xuyRF7I',
                'Content-Type: application/json'
            ];

            if (count($notification_database_data) > 0) {
                NotificationDetail::insert($notification_database_data);
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $fcmUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcmNotification));
            curl_exec($ch);
            curl_close($ch);
        }
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}

function getMembers($parentId)
{
    $members = array();
    $member_details = MemberDetail::where('ptId', $parentId)->get();
    foreach (count($member_details) > 0 ? $member_details : array() as $m) {
        array_push($members, ($m->memberData ? $m->memberData->name : ""));
    }
    $invitation_sent = InvitationDetail::select('memberEmailID')
        ->where('ptId', $parentId)
        ->where('status', config('constants.invitation_status')['pending'])
        ->get();
    foreach (count($invitation_sent) > 0 ? $invitation_sent : array() as $i) {
        array_push($members, $i->memberEmailID);
    }
    return $members;
}

function getProjectStatus($project)
{
    if (session('user_details')) {
        $current_time = session('user_details')->timezone != '' ? session('user_details')->timezone : config('app.timezone');
    } else {
        $current_time = Auth::user()->timezone != '' ? Auth::user()->timezone : config('app.timezone');

    }
    $dueDateTime =
        $project->dueDateTime != '' ? $project->dueDateTime : '00:00';
    $is_overdue = 0;
    if ($project->status != config('constants.project_status.completed')) {
        $is_overdue =
            $project->dueDate != '' &&
            Carbon::parse($project->dueDate . ' ' . $dueDateTime) <
            Carbon::now($current_time)->format('Y-m-d H:i')
                ? 1
                : 0;
    }
    return array(
        'status' => $project->status,
        'is_overdue' => $is_overdue
    );
}

function getProjectStatusWeb($project)
{
    $current_time =
        Auth::user()->timezone != ''
            ? Auth::user()->timezone
            : config('app.timezone');
    $dueDateTime =
        $project->dueDateTime != '' ? $project->dueDateTime : '00:00';
    $is_overdue = 0;
    if ($project->status != config('constants.project_status.completed')) {
        $is_overdue =
            $project->dueDate != '' &&
            Carbon::parse($project->dueDate . ' ' . $dueDateTime) <
            Carbon::now($current_time)->format('Y-m-d H:i')
                ? 1
                : 0;
    }
    return array(
        'status' => $project->status,
        'is_overdue' => $is_overdue
    );
}

function convertCommentDate($timezone, $date)
{
    $current_timezone = $timezone != '' ? $timezone : config('app.timezone');
    return Carbon::parse($date)->tz($current_timezone)->format('M d, h:ia');
}

function convertAttachmentDate($timezone, $date)
{
    $current_timezone = $timezone != '' ? $timezone : config('app.timezone');
    return Carbon::parse($date)->tz($current_timezone)->format('M d, h:ia');
}

function getPusherObject()
{
    $options = array(
        'cluster' => env('PUSHER_APP_CLUSTER'),
        'encrypted' => true
    );
    $pusher = new Pusher(
        env('PUSHER_APP_KEY'),
        env('PUSHER_APP_SECRET'),
        env('PUSHER_APP_ID'),
        $options
    );
    return $pusher;
}

function getMemberNames($ptid, $user_id, $is_task)
{
    $member_emails = array();
    $member_names = array();
    $member_details = MemberDetail::where("ptId", $ptid)->where("memberId", "!=", $user_id)->get();
    foreach (count($member_details) > 0 ? $member_details : array() as $m) {
        if ($m->memberData) {
            array_push($member_emails, $m->memberData->email);
            array_push($member_names, $m->memberData->name);
        }
    }
    if (!$is_task) {
        $invitation_sent = InvitationDetail::select("memberEmailID")->where("ptId", $ptid)->where("status", config("constants.invitation_status")["pending"])->get();
        foreach (count($invitation_sent) > 0 ? $invitation_sent : array() as $i) {
            array_push($member_emails, $i->memberEmailID);
            array_push($member_names, $i->memberEmailID);
        }
    }
    return array("member_emails" => implode(",", $member_emails), "member_names" => implode(",", $member_names));
}

function sendToReviewProject($pt)
{
    $status = config("constants.project_status.active");
    if ($pt->parentId != 0 && $pt->parentProject && $pt->parentProject->type != config("constants.type.task")) {
        $get_subprojects = ProjectTaskDetail::where("parentId", $pt->parentId)->where("type", config("constants.type.project"))->where("parentLevel", 2)->where("status", "!=", config("constants.project_status.completed"))->count();
        $get_tasks = ProjectTaskDetail::where("parentId", $pt->parentId)->where("type", config("constants.type.task"))->where("status", "!=", config("constants.project_status.completed"))->count();
        $complete_count = $get_subprojects + $get_tasks;
        if ($complete_count == 0) {
            $parent_data = ProjectTaskDetail::where("id", $pt->parentId)->first();
            if ($parent_data && $parent_data->status != config("constants.project_status.completed")) {
                $parent_data->status = $status = config("constants.project_status.review");
                $parent_data->save();
            }
        }
    }
    return $status;
}

function repeatChildProjectTask($old_data, $parent_repeat, $is_web = 0, $timezone = NULL)
{
    $new_data = repeatData($old_data, $parent_repeat, $timezone);
    if ($is_web) {
        return $new_data;
    }
    $details = array();
    if ($new_data && $timezone == NULL) {
        $details = array("id" => $old_data->id, "name" => $old_data->name, "original_due_date" => $old_data->dueDate, "original_due_date_time" => $old_data->dueDateTime, "due_date" => getDueDateTime($old_data->dueDate, $old_data->dueDateTime), "flag" => $old_data->flag, "total_comments" => 0, "total_documents" => 0, "tags" => getTagsName($old_data->tags), "type" => array_search($old_data->type, config('constants.type')), "is_creator_of_project" => ($old_data->createdBy == session("user_details")->id ? 1 : 0), "repeat" => $old_data->repeat, "reminder" => $old_data->reminder, "level" => $old_data->parentLevel, "parent_id" => $old_data->parentId);
        if ($details["type"] == "task") {
            $details["is_assigned"] = 1;
            $details["parent_project_name"] = ($old_data->parentProject ? $old_data->parentProject->name : "");
            $details["parent_project_color"] = ($old_data->parentProject ? $old_data->parentProject->color : "");
        }
        $get_status = getProjectStatus($old_data);
        $details["status"] = $get_status["status"];
        $details["is_overdue"] = $get_status["is_overdue"];
    }
    return $details;
}

function repeatData($old_data, $parent_repeat, $timezone)
{
    $oldDueDate = $old_data->dueDate;
    $oldDueDateTime = $old_data->dueDateTime;
    $oldStatus = $old_data->status;

    $new_date = $dueDate = "";
    if ($old_data->dueDate != "") {
        $dueDateTime = ($old_data->dueDateTime != "" ? $old_data->dueDateTime : "00:00");
        $dueDate = Carbon::parse($old_data->dueDate . " " . $dueDateTime);

        $pt_repeat = $parent_repeat;
        $pt_repeat_array = explode(" ", $pt_repeat);
        $is_add = 0;
        if (count($pt_repeat_array) > 0) {
            unset($pt_repeat_array[0]);
            if (count($pt_repeat_array) == 1) {
                $pt_repeat = "1 " . strtolower($pt_repeat_array[1]);
            } else {
                $is_add = 1;
                $pt_repeat = $pt_repeat_array[1] . " " . strtolower($pt_repeat_array[2]);
            }
        }
        $new_date = Carbon::parse($old_data->dueDate . " " . $dueDateTime)->add($pt_repeat);
        if ($is_add) {
            $dueDate = $new_date;
        }
    }
    $is_save = 0;
    if ($timezone != NULL) {
        if ($dueDate != "" && $dueDate->equalTo(Carbon::now($timezone)->format("Y-m-d H:i:00"))) {
            $is_save = 1;
        }
    } else {
        if ($dueDate != "") {
            $is_save = 1;
        }
    }
    if ($is_save) {
        if ($new_date != "") {
            $old_data->dueDate = $new_date->format("Y-m-d");
            $old_data->dueDateTime = $new_date->format("H:i");
        }
        $old_data->status = config("constants.project_status.active");
        $old_data->send_to_review_by = NULL;
        $old_data->send_to_review_time = NULL;
        $old_data->save();

        $log_data = new StatusLogDetail();
        $log_data->ptId = $old_data->id;
        $log_data->oldDueDate = $oldDueDate;
        $log_data->oldDueDateTime = $oldDueDateTime;
        $log_data->status = $oldStatus;
        $log_data->userBy = (session("user_details") ? session("user_details")->id : (Auth::user() ? Auth::user()->id : 0));
        $log_data->actionTime = Carbon::now()->format("Y-m-d H:i:s");
        $log_data->save();

        $get_childs = ProjectTaskDetail::where("parentId", $old_data->id)->get();
        foreach (count($get_childs) ? $get_childs : array() as $gc) {
            repeatData($gc, $parent_repeat, $timezone);
        }
        return true;
    } else {
        return false;
    }

}


function changeActiveStatusChilds($project_id)
{
    $first_level_child = ProjectTaskDetail::where("parentId", $project_id)->get();
    foreach (count($first_level_child) > 0 ? $first_level_child : array() as $f) {
        $f->status = config("constants.project_status.active");
        $f->save();
        changeActiveStatusChilds($f->id);
    }
}


function splitDocumentName($document_name)
{
    $get_name = explode("iT@sk$", $document_name);
    return count($get_name) > 0 ? end($get_name) : $document_name;
}

/**
 * Used to send notification when update project/task
 */
function updateNotification($project_detail, $parent_id, $parent_data)
{
    $id = $project_detail->id;
    $all_members = MemberDetail::where("ptId", $id)->get();
    $device_tokens = array();
    $type = array_search($project_detail->type, config('constants.type'));
    $old_all_parent_ids = getBaseParentId($parent_data["old_parent"], array());
    $all_child_ids = getChildIds($project_detail->id, array());
    $new_all_parent_ids = getBaseParentId($parent_data["new_parent"], array());
    foreach (count($all_members) > 0 ? $all_members : array() as $am) {
        if (isset($am->memberData)) {
            $memberData = $am->memberData;
            if ($memberData->deviceToken != "" && $memberData->type == intval(config('constants.device_type.ios'))) {
                $device_tokens[$memberData->id] = $memberData->deviceToken;
            }
        }
        if ($parent_data["isParent_changed"]) {
            $childs = array_merge($old_all_parent_ids, $all_child_ids, $new_all_parent_ids);
        } else {
            $childs = array_merge($old_all_parent_ids, $all_child_ids);
        }
        $pusher = getPusherObject();
        $data = array("user_id" => $am->memberId, "text" => "", "type" => "refresh_list", "pt_id" => $parent_id, "all_ids" => implode(",", $childs));
        $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($am->memberId), 'App\\Events\\ProjectEvent', $data);
    }
    if (count($device_tokens) > 0) {
        $pass_parameter["type"] = ($type == "project" ? "update_project" : "update_task");
        $pass_parameter["subtype"] = $type;
        $pass_parameter["task_id"] = $id;
        $pass_parameter["parent_id"] = (string)$project_detail->parentId;
        $pass_parameter["details"] = listMethodData($project_detail, (session("user_details") ? session("user_details")->id : Auth::user()->id));
        $pass_parameter["all_parent_ids"] = implode(",", $old_all_parent_ids);
        $pass_parameter["all_child_ids"] = implode(",", $all_child_ids);
        $pass_parameter["is_parent_changed"] = $parent_data["isParent_changed"];
        $pass_parameter["new_all_parent_ids"] = "";
        $pass_parameter["new_parent_id"] = "";
        if ($parent_data["isParent_changed"]) {
            $pass_parameter["new_all_parent_ids"] = implode(",", $new_all_parent_ids);
            $pass_parameter["new_parent_id"] = ($parent_data["new_parent_data"] != "" ? getFirstParentID($parent_data["new_parent_data"]) : 0);
        }
        $pass_parameter["first_parent_project_id"] = ($project_detail->parentProject ? getFirstParentID($project_detail->parentProject) : 0);

        sendSlientNotificationIOS($device_tokens, $pass_parameter);
    }
}

/**
 * get project details
 */
function getProjectDetails($get_project_details)
{
    $project_details = array();
    $user_id = (session("user_details") ? session("user_details")->id : Auth::user()->id);
    $project_details["id"] = $get_project_details->id;
    $project_details["name"] = $get_project_details->name;
    $project_details["dueDate"] = $get_project_details->dueDate;
    $project_details["dueDateTime"] = $get_project_details->dueDateTime;
    $project_details["repeat"] = $get_project_details->repeat;
    $project_details["reminder"] = $get_project_details->reminder;
    $project_details["flag"] = $get_project_details->flag;
    $project_details["color"] = $get_project_details->color;
    $project_details["note"] = $get_project_details->note;
    $project_details["currentParentId"] = $get_project_details->parentId;
    $project_details["status"] = $get_project_details->status;
    $project_details["tags"] = getTagsName($get_project_details->tags);
    $project_details["parentId"] = $get_project_details->parentId;
    $project_details["parentLevel"] = $get_project_details->parentLevel;
    $project_details["order"] = $get_project_details->ptOrder;
    $project_details["parentProjectName"] = ($get_project_details->parentProject ? $get_project_details->parentProject->name : "");
    $project_details["parentProjectStatus"] = ($get_project_details->parentProject ? $get_project_details->parentProject->status : 1);

    $members = getMemberNames($get_project_details->id, $user_id, 0);
    $project_details["members"] = $members["member_emails"];
    $project_details["member_names"] = $members["member_names"];
    $project_details["is_creator_of_project"] = ($get_project_details->createdBy == $user_id ? 1 : 0);
    $isAssigned = 0;
    if (array_search($get_project_details->type, config('constants.type')) == "task") {
        $is_member = MemberDetail::where("ptId", $get_project_details->id)->where("memberId", $user_id)->count();
        $isAssigned = ($is_member ? 1 : 0);
        if ($get_project_details->parentProject) {
            $parent_data = getBaseParentData($get_project_details->parentProject);
            if (isset($parent_data["parent_data"])) {
                $project_details['parentProjectName'] = $parent_data["parent_data"]->name;
                $project_details['parentProjectStatus'] = $parent_data["parent_data"]->status;
                $project_details['parentId'] = $parent_data["parent_data"]->id;
            } else {
                $project_details['parentId'] = $parent_data["parent_id"];
                $project_details["parentProjectName"] = "";
                $project_details["parentProjectStatus"] = 1;
            }
        }
    }
    $project_details["is_assigned"] = $isAssigned;
    return $project_details;
}

/**
 * Send notification for comment
 */
function sendCommentNotification($project_data, $type)
{
    $remaining_user_token = $remaining_users = array();
    $member_details = MemberDetail::where("ptId", $project_data->id)->get();
    foreach (count($member_details) > 0 ? $member_details : array() as $m) {
        if (isset($m->memberData)) {
            $memberData = $m->memberData;
            if ($memberData->deviceToken != "" && $memberData->type == intval(config('constants.device_type.ios'))) {
                $remaining_user_token[$memberData->id] = $memberData->deviceToken;
            }
            array_push($remaining_users, $memberData->id);
        }
    }
    $pass_parameter["type"] = $type;
    $pass_parameter["subtype"] = array_search($project_data->type, config('constants.type'));
    $pass_parameter["project_id"] = $project_data->id;
    $pass_parameter["project_name"] = $project_data->name;
    $pass_parameter["CmtCount"] = CommentDetail::where('pt_id', $project_data->id)->count();

    if (count($remaining_user_token) > 0) {
        sendSlientNotificationIOS($remaining_user_token, $pass_parameter);
    }
    $childs = "";
    if ($type == "delete_comment") {
        $all_parent_ids = getBaseParentId($project_data, array());
        $all_child_ids = getChildIds($project_data->id, array());
        $childs = array_merge($all_parent_ids, $all_child_ids);
    }
    foreach ($remaining_users as $ru) {
        $pusher = getPusherObject();
        $data = array("user_id" => $ru, "text" => "", "type" => "comment_list", "pt_id" => intval($project_data->parentId), "comment_pt_id" => $project_data->id, "all_ids" => $childs);
        $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($ru), 'App\\Events\\ProjectEvent', $data);
    }
}

/**
 * Send notification for attachment
 */
function sendAttachmentNotification($project_data, $type)
{
    $remaining_user_token = $remaining_users = array();
    $member_details = MemberDetail::where("ptId", $project_data->id)->get();
    foreach (count($member_details) > 0 ? $member_details : array() as $m) {
        if (isset($m->memberData)) {
            $memberData = $m->memberData;
            if ($memberData->deviceToken != "" && $memberData->type == intval(config('constants.device_type.ios'))) {
                $remaining_user_token[$memberData->id] = $memberData->deviceToken;
            }
            array_push($remaining_users, $memberData->id);
        }
    }
    $pass_parameter["type"] = $type;
    $pass_parameter["subtype"] = array_search($project_data->type, config('constants.type'));
    $pass_parameter["project_id"] = $project_data->id;
    $pass_parameter["project_name"] = $project_data->name;
    $pass_parameter["attachment_count"] = DocumentDetail::where('ptId', $project_data->id)->count();

    if (count($remaining_user_token) > 0) {
        sendSlientNotificationIOS($remaining_user_token, $pass_parameter);
    }
    $all_parent_ids = getBaseParentId($project_data, array());
    $all_child_ids = getChildIds($project_data->id, array());
    $childs = array_merge($all_parent_ids, $all_child_ids);
    foreach ($remaining_users as $ru) {
        $pusher = getPusherObject();
        $data = array("user_id" => $ru, "text" => "", "type" => "attachment_list", "pt_id" => intval($project_data->parentId), "attachment_pt_id" => $project_data->id, "all_ids" => $childs);
        $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($ru), 'App\\Events\\ProjectEvent', $data);
    }
}

function listMethodData($data, $userId)
{
    $totalComment = CommentDetail::where('pt_id', $data->id)->count();
    $documents = DocumentDetail::where('ptId', $data->id)->count();
    $formatted_due_date = getDueDateTime($data->dueDate, $data->dueDateTime);
    $details = array(
        'id' => $data->id,
        'name' => $data->name,
        'original_due_date' => $data->dueDate,
        'original_due_date_time' => $data->dueDateTime,
        'due_date' => $formatted_due_date,
        'flag' => $data->flag,
        'total_comments' => $totalComment,
        'total_documents' => $documents,
        'tags' => getTagsName($data->tags),
        'type' => array_search($data->type, config('constants.type')),
        'is_creator_of_project' => $data->createdBy == $userId ? 1 : 0,
        'reminder' => $data->reminder,
        'level' => $data->parentLevel,
        'parent_id' => $data->parentId,
        'repeat' => $data->repeat,
        'maxChildLevel' => 0,
        'order' => $data->ptOrder
    );
    if ($details['type'] == 'task') {
        $is_member = MemberDetail::where('ptId', $data->id)->where('memberId', $userId)->count();
        $details['is_assigned'] = $is_member ? 1 : 0;
        $details['creator_name'] = ($data->creatorData ? $data->creatorData->name : "");
    }
    $members = getMemberNames($data->id, $data->createdBy, 1);
    $details['member_names'] = $members["member_names"];
    $details['member_emails'] = $members["member_emails"];
    $details['parent_project_name'] = $data->parentProject ? $data->parentProject->name : '';
    $details['parent_project_color'] = $data->parentProject ? $data->parentProject->color : '';
    $details['parent_project_status'] = $data->parentProject ? $data->parentProject->status : 1;
    $status = getProjectStatus($data);
    $details['status'] = $status['status'];
    $details['is_overdue'] = $status['is_overdue'];
    if ($details['type'] == 'task' && $data->parentProject) {
        $parent_data = getBaseParentData($data->parentProject);
        if (isset($parent_data["parent_data"])) {
            $details['parent_project_name'] = $parent_data["parent_data"]->name;
            $details['parent_project_color'] = $parent_data["parent_data"]->color;
            $details['parent_project_status'] = $parent_data["parent_data"]->status;
            $details['base_parent_id'] = $parent_data["parent_data"]->id;
            $details['base_parent_level'] = $parent_data["parent_data"]->parentLevel;
        } else {
            $details['base_parent_id'] = $parent_data["parent_id"];
            $details['base_parent_level'] = $parent_data["parent_level"];
        }
    } else {
        $details['base_parent_id'] = 0;
        $details['base_parent_level'] = ($details['type'] == 'task' ? 0 : 1);
    }
    if ($details['base_parent_id'] == 0 && $details['type'] == 'task') {
        $details['parent_project_name'] = $details['parent_project_color'] = "";
        $details['parent_project_status'] = 1;
    }
    return $details;
}

function getBaseParentId($project_data, $result, $is_string = 0)
{
    if (intval($project_data->parentId) == 0 && $project_data->type == config("constants.type.task")) {
        array_push($result, $project_data->parentId);
    } else {
        $result = recursiveBaseParentId($project_data->parentId, array());
    }
    return ($is_string ? (count($result) > 0 ? implode(",", $result) : "") : $result);
}

function recursiveBaseParentId($project_id, $result)
{
    $project = ProjectTaskDetail::selectRaw("parentId,type")->where("id", $project_id)->first();
    if ($project) {
        array_push($result, $project_id);
        if ($project->type == config("constants.type.task") && intval($project->parentId) == 0) {
            array_push($result, $project->parentId);
        } else {
            $result = recursiveBaseParentId($project->parentId, $result);
        }
    }
    return $result;
}

function getBaseParentData($parent_data)
{
    $result = array("parent_data" => $parent_data);
    $project = ProjectTaskDetail::where("id", $parent_data->id)->first();
    if ($project && $project->parentProject) {
        if ($project->type == config("constants.type.project")) {
            return $result;
        } else {
            $result = getBaseParentData($project->parentProject);
        }
    } else if ($project->type == config("constants.type.task")) {
        $result = array("parent_id" => 0, "parent_level" => 0);
    }
    return $result;
}

function getFirstParentID($parent_data)
{
    $result = $parent_data->id;
    $project = ProjectTaskDetail::where("id", $parent_data->id)->first();
    if ($project && $project->parentProject) {
        if ($project->type == config("constants.type.project")) {
            return $result;
        } else {
            $result = getFirstParentID($project->parentProject);
        }
    } else if ($project->type == config("constants.type.task")) {
        $result = 0;
    }
    return $result;
}

function getChildIds($project_id, $result, $is_string = 0)
{
    $result = recursiveChildIds($project_id, array());
    return ($is_string ? (count($result) > 0 ? implode(",", $result) : "") : $result);
}

function recursiveChildIds($project_id, $result)
{
    $childs = ProjectTaskDetail::select("id")->where("parentId", $project_id)->get();
    foreach (count($childs) > 0 ? $childs : array() as $c) {
        array_push($result, $c->id);
        $result = recursiveChildIds($c->id, $result);
    }
    return $result;
}

function findLastOrder($project_id)
{
    $order = 1;
    if (intval($project_id) != 0) {
        $get_latest_order = ProjectTaskDetail::selectRaw('MAX(ptOrder) as max_order')->where('parentId', $project_id)->first();
        if ($get_latest_order && $get_latest_order->max_order != 0) {
            $order = $get_latest_order->max_order + 1;
        }
    }
    return $order;
}

function refreshOrder($parent_id, $order_para, $type, $userId)
{
    if (intval($parent_id) != 0) {
        $childs = ProjectTaskDetail::where("parentId", $parent_id)->orderBy("ptOrder", "asc")->get();
        foreach (count($childs) > 0 ? $childs : array() as $c) {
            $c->ptOrder = ++$order_para;
            $c->save();
        }
    } else if ($type == config("constants.type.task")) {
        $childs = ProjectTaskDetail::where("parentId", 0)->where("type", config("constants.type.task"))->where("createdBy", $userId)->orderBy("ptOrder", "asc")->get();
        foreach (count($childs) > 0 ? $childs : array() as $p) {
            $p->ptOrder = ++$order_para;
            $p->save();
        }
    }
}

function refreshOldParentOrder($parent_id, $order, $type, $userId)
{
    $parent_id = intval($parent_id);
    if ($parent_id != 0) {
        $childs = ProjectTaskDetail::where("parentId", $parent_id)->where("ptOrder", ">", $order)->orderBy("ptOrder", "asc")->get();
        foreach (count($childs) > 0 ? $childs : array() as $c) {
            $c->ptOrder = $order++;
            $c->save();
        }
    } else if ($type == config("constants.type.task")) {
        $childs = ProjectTaskDetail::where("parentId", 0)->where("type", config("constants.type.task"))->where("ptOrder", ">", $order)->where("createdBy", $userId)->orderBy("ptOrder", "asc")->get();
        foreach (count($childs) > 0 ? $childs : array() as $p) {
            $p->ptOrder = $order++;
            $p->save();
        }
    }
}

function getTaskTotal($id, $user_id)
{
    $childs = getChildIds($id, array());
    return ProjectTaskDetail::join('member_details', 'project_task_details.id', '=', 'member_details.ptId')->whereIn("project_task_details.id", $childs)->where("member_details.memberId", $user_id)->where("project_task_details.type", config("constants.type.task"))->whereRaw('member_details.deleted_at is null')->where("project_task_details.status", "!=", config("constants.project_status.completed"))->count();
}

function sendReorderNotification($parent_id, $project_data, $is_send_web = 1)
{
    $parent_id = intval($parent_id);
    $project_detail = ProjectTaskDetail::where("id", $parent_id)->first();

    if ($parent_id != 0) {
        $all_members = MemberDetail::where("ptId", $parent_id)->get();
    } else {
        $all_members = MemberDetail::where("ptId", $project_data->id)->get();
    }
    $device_tokens = $users = array();
    foreach (count($all_members) > 0 ? $all_members : array() as $am) {
        if (isset($am->memberData)) {
            $memberData = $am->memberData;
            if ($memberData->deviceToken != "" && $memberData->type == intval(config('constants.device_type.ios'))) {
                $device_tokens[$memberData->id] = $memberData->deviceToken;
            }
            array_push($users, $memberData->id);
        }
    }
    if (count($device_tokens) > 0) {
        $pass_parameter["type"] = "reorder";
        $pass_parameter["subtype"] = ($project_detail ? array_search($project_detail->type, config('constants.type')) : "inbox");
        $pass_parameter["parent_id"] = $parent_id;
        $pass_parameter["all_parent_ids"] = getBaseParentId($project_data, array(), 1);
        sendSlientNotificationIOS($device_tokens, $pass_parameter);
    }
    if ($is_send_web) {
        foreach (count($users) > 0 ? $users : array() as $ru) {
            $pusher = getPusherObject();
            $all_parent_ids = getBaseParentId($project_data, array());
            $all_child_ids = getChildIds($project_data->id, array());
            if (count($all_child_ids) > 0) {
                $all_parent_ids = array_merge($all_parent_ids, $all_child_ids);
            }
            $childs = implode(",", $all_parent_ids);
            $data = array("user_id" => $ru, "text" => "", "type" => "refresh_list", "pt_id" => ($project_data->type == config("constants.type.project") ? $project_data->id : $parent_id), "all_ids" => $childs);
            $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($ru), 'App\\Events\\ProjectEvent', $data);
        }
    }
}

function sendLogSlientNotificationIOS($deviceToken, $passParameter,$member_timezone)
{
    try {
        $options = [
            'key_id' => '338K9DWA35', // The Key ID obtained from Apple developer account
            'team_id' => '638BW42L7E', // The Team ID obtained from Apple developer account
            'app_bundle_id' => 'com.intellidt.LogApp', // The bundle ID for app obtained from Apple developer account
            'private_key_path' =>
                url('notification') . '/AuthKey_338K9DWA35.p8', // Path to private key
            'private_key_secret' => null // Private key secret
        ];
        $authProvider = AuthProvider\Token::create($options);
        $notifications = [];
        foreach (count($deviceToken) > 0 ? $deviceToken : array() as $d => $value) {
            $alert = Alert::create();
            $alert = $alert->setBody($passParameter["custom_text"]);
            $payload = Payload::create()->setAlert($alert);
            $payload->setCustomValue('type', $passParameter['type']);
            $payload->setCustomValue('chat_id', $passParameter['chat_id']);
            if(count($member_timezone) > 0 && isset($member_timezone[$d])) {
                $payload->setCustomValue('chat_date', $member_timezone[$d]);
            }
            $payload->setSound('default');
            $payload->setContentAvailability(true);
            $notifications[] = new Notification($payload, $value);
        }
        if (count($notifications) > 0) {
            $client = new Client($authProvider, false);
            $client->addNotifications($notifications);
            $client->push();
        }
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}
