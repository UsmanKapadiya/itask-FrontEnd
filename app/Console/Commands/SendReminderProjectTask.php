<?php

namespace App\Console\Commands;

use App\Models\UserDetail;
use App\Notifications\MailNotification;
use Illuminate\Console\Command;
use App\Models\ProjectTaskDetail;
use App\Models\MemberDetail;
use Carbon\Carbon;
use Notification;

class SendReminderProjectTask extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'It is used to send notification/email before due date for project/task';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $all_users = UserDetail::where("isVerified", 1)->whereIn("type", array(config("constants.device_type.ios"), config("constants.device_type.android")))->get();
            foreach (count($all_users) > 0 ? $all_users : array() as $u) {
                $current_time = ($u->timezone != "" ? $u->timezone : config("app.timezone"));
                $get_projects = ProjectTaskDetail::join("member_details", "project_task_details.id", "=", "member_details.ptId")->selectRaw("project_task_details.*,member_details.ptId,member_details.memberId")->where("member_details.memberId", $u->id)->whereRaw('(project_task_details.duedate != "" and project_task_details.dueDate >= "' . Carbon::now($current_time)->format("Y-m-d") . '")')->where("project_task_details.status", "!=", config("constants.project_status.completed"))->orderBy("project_task_details.id", "desc")->get();
                foreach (count($get_projects) > 0 ? $get_projects : array() as $pt) {
                    $type = array_search($pt->type, config('constants.type'));
                    $parent_project_name = ($pt->parentProject ? ($pt->parentProject->name) : "");
                    $dueDateTime = ($pt->dueDateTime != "" ? $pt->dueDateTime : "00:00");
                    $dueDate = Carbon::parse($pt->dueDate . " " . $dueDateTime);

                    $project_due_text = config("notificationText.project_due");
                    $project_due_text = str_replace(array("{name}", "{date}"), array($pt->name, Carbon::parse($pt->dueDate)->format("M j")), $project_due_text);
                    $subject = (config('app.name') . " " . Carbon::now($current_time)->format("M j") . " (1 $type due)");
                    $is_send_notification = $is_send_email = $is_send_push_notification = 0;
                    if ($pt->reminder != "None" && $pt->reminder != "") {
                        if (isset(config('reminder')[$pt->reminder])) {
                            if ($pt->reminder != "At time of event") {
                                $dueDate = $dueDate->sub(config('reminder')[$pt->reminder]);
                            }
                            if ($dueDate->equalTo(Carbon::now($current_time)->format("Y-m-d H:i:00"))) {
                                if ($u->remind_via_mobile_notification)
                                    $is_send_notification = 1;
                                if ($u->remind_via_email)
                                    $is_send_email = 1;
                                if($u->remind_via_desktop_notification)
                                    $is_send_push_notification = 1;
                            }
                        }
                    } else if ($u->automatic_reminder != "None") {
                        if (isset(config('reminder')[$u->automatic_reminder])) {
                            if ($u->automatic_reminder != "At time of event") {
                                $dueDate = $dueDate->sub(config('reminder')[$u->automatic_reminder]);
                            }
                            if ($dueDate->equalTo(Carbon::now($current_time)->format("Y-m-d H:i:00"))) {
                                if ($u->remind_via_mobile_notification)
                                    $is_send_notification = 1;
                                if ($u->remind_via_email)
                                    $is_send_email = 1;
                                if($u->remind_via_desktop_notification)
                                    $is_send_push_notification = 1;
                            }
                        }
                    }
                    if ($is_send_notification && $u->deviceToken != "") {
                        $pass_parameter["type"] = "reminder";
                        $pass_parameter["subtype"] = $type;
                        if ($type == "project") {
                            $pass_parameter["project_id"] = $pt->id;
                            $pass_parameter["project_name"] = $pt->name;
                            $pass_parameter["project_status"] = $pt->status;
                        } else {
                            $pass_parameter["task_id"] = $pt->id;
                            $pass_parameter["project_id"] = intval($pt->parentId);
                            $pass_parameter["project_name"] = $parent_project_name;
                            $pass_parameter["project_status"] = ($pt->parentProject ? ($pt->parentProject->status) : 1);

                        }
                        if ($u->type == intval(config('constants.device_type.ios'))) {
                            sendNotificationIOS(array($u->id => $u->deviceToken), $project_due_text, config("notificationText.project_due"), array(), "project_due", $pass_parameter, $pt->id);
                        } else {
                            sendNotificationAndroid(array($u->id => $u->deviceToken), $project_due_text, config("notificationText.project_due"), array(), "project_due", $pass_parameter, $pt->id);
                        }
                    }
                    if ($is_send_email) {
                        Notification::send($u, new MailNotification(array('text' => $project_due_text, 'subtext' => ($parent_project_name != "" ? ($pt->name . " . " . $parent_project_name) : ""), 'btntext' => ('View this ' . $type), 'subject' => $subject)));
                    }
                    if($is_send_push_notification){
                        $pusher = getPusherObject();
                        $data = array("user_id" => $u->id, "text" => $project_due_text, "type" => "reminder", "pt_id" => ($type == "project" ? $pt->id : $pt->parentId));
                        $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($u->id), 'App\\Events\\ProjectEvent', $data);
                    }
                }
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}
